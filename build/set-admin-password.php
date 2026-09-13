<?php

/**
 * Sets the Super User password of the local test site.
 *
 * Joomla's installer refuses passwords under twelve characters, and com_users
 * applies the same rule in the interface. The test site wants a short, memorable
 * one, so the hash is written straight to the users table. PHP's password_hash()
 * produces exactly what Joomla verifies against, so nothing about the login
 * changes - only the rule about how the password was chosen is bypassed.
 *
 * Local development only. Credentials come from the git-ignored .env.
 *
 * Usage: php build/set-admin-password.php
 */

declare(strict_types=1);

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

$required = ['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_NAME', 'DB_PREFIX', 'ADMIN_USER', 'ADMIN_PASSWORD'];

foreach ($required as $key) {
    if (!\array_key_exists($key, $settings)) {
        fwrite(STDERR, "Missing $key in .env\n");
        exit(1);
    }
}

if ($settings['ADMIN_PASSWORD'] === '') {
    fwrite(STDERR, "ADMIN_PASSWORD is empty in .env\n");
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

// The table prefix comes from configuration, not from user input, but it is
// still interpolated into the statement - so it is checked rather than trusted.
if (!preg_match('/^[A-Za-z0-9_]+$/', $settings['DB_PREFIX'])) {
    fwrite(STDERR, "The table prefix must be alphanumeric.\n");
    exit(1);
}

$hash      = password_hash($settings['ADMIN_PASSWORD'], PASSWORD_DEFAULT);
$statement = $mysqli->prepare(
    'UPDATE `' . $settings['DB_PREFIX'] . 'users` SET `password` = ? WHERE `username` = ?'
);

if ($statement === false) {
    fwrite(STDERR, 'Cannot prepare the update: ' . $mysqli->error . "\n");
    exit(1);
}

$statement->bind_param('ss', $hash, $settings['ADMIN_USER']);
$statement->execute();

if ($statement->affected_rows < 1) {
    fwrite(STDERR, \sprintf("No user named \"%s\" was updated.\n", $settings['ADMIN_USER']));
    exit(1);
}

printf("Password set for %s.\n", $settings['ADMIN_USER']);

$statement->close();
$mysqli->close();
