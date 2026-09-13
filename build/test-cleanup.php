<?php

/**
 * Removes the blueprints the Cypress suite creates.
 *
 * Called from the specs through cy.exec(), before and after the run: before, so
 * a crashed run cannot leave rows that make the next one ambiguous; after, so a
 * finished run leaves the site as it found it.
 *
 * Only rows whose title starts with the test marker are deleted. That is the
 * whole safety mechanism: this never truncates the table and never touches a
 * blueprint somebody wrote by hand, even when both live in the same database.
 *
 * Local development only. Credentials come from the git-ignored .env.
 *
 * Usage: php build/test-cleanup.php [marker]
 */

declare(strict_types=1);

const DEFAULT_MARKER = '[cypress] ';

$root = \dirname(__DIR__);
$env  = $root . '/.env';

if (!is_file($env)) {
    fwrite(STDERR, "No .env file; copy .env.example and fill it in.\n");
    exit(1);
}

$settings = [];

foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);

    $settings[trim($key)] = trim(trim($value), '"\'');
}

foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_NAME', 'DB_PREFIX'] as $key) {
    if (!\array_key_exists($key, $settings)) {
        fwrite(STDERR, "Missing $key in .env\n");
        exit(1);
    }
}

$marker = $argv[1] ?? DEFAULT_MARKER;

if (trim($marker) === '') {
    fwrite(STDERR, "The marker cannot be empty: that would match every blueprint.\n");
    exit(1);
}

// The prefix comes from configuration rather than user input, but it is
// interpolated into the statement, so it is checked rather than trusted.
if (!preg_match('/^[A-Za-z0-9_]+$/', $settings['DB_PREFIX'])) {
    fwrite(STDERR, "The table prefix must be alphanumeric.\n");
    exit(1);
}

$mysqli = @new mysqli(
    $settings['DB_HOST'],
    $settings['DB_USER'],
    $settings['DB_PASSWORD'] ?? '',
    $settings['DB_NAME'],
    (int) $settings['DB_PORT']
);

if ($mysqli->connect_errno !== 0) {
    fwrite(STDERR, 'Cannot connect: ' . $mysqli->connect_error . "\n");
    exit(1);
}

$table = $settings['DB_PREFIX'] . 'pluggen_blueprints';

// A site without the component installed is not an error worth failing a test
// run over - there is simply nothing to clean.
if ($mysqli->query('SHOW TABLES LIKE ' . "'" . $mysqli->real_escape_string($table) . "'")->num_rows === 0) {
    echo "No blueprints table; nothing to clean.\n";
    exit(0);
}

$statement = $mysqli->prepare('DELETE FROM `' . $table . '` WHERE `title` LIKE ?');

if ($statement === false) {
    fwrite(STDERR, 'Cannot prepare the delete: ' . $mysqli->error . "\n");
    exit(1);
}

// Escape the wildcards in the marker itself, so a marker containing % or _
// still matches literally.
$pattern = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $marker) . '%';

$statement->bind_param('s', $pattern);
$statement->execute();

printf("Removed %d test blueprint(s) marked \"%s\".\n", $statement->affected_rows, $marker);

$statement->close();
$mysqli->close();
