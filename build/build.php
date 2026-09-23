<?php

/**
 * Assembles the installable component package from src/.
 *
 * The version comes from the manifest, which is the single place it is written:
 * the archive is named after it, and the release workflow refuses to publish a
 * tag that disagrees with it. Releasing is then a matter of editing one line.
 *
 * Entry names always use forward slashes, and the archive contains the manifest
 * at its root - a wrapper folder or a backslash separator both break the install
 * on a Linux host.
 *
 * Since 4.1 the package also carries the shared library, under `library/`, where
 * `script.php` looks for it. Joomla has no way for a manifest to declare a
 * dependency on a library, so an extension that needs one ships it - and the
 * version bundled is read out of `script.php`, so the build cannot produce a
 * package whose own install script refuses the library inside it.
 *
 *   php build/build.php                     the newest local build, else the release
 *   php build/build.php --library=one.zip   that one
 *   php build/build.php --no-library        none, for a site that already has it
 */

declare(strict_types=1);

$root   = \dirname(__DIR__);
$source = $root . '/src';

if (!is_dir($source)) {
    fwrite(STDERR, "No src/ directory found.\n");
    exit(1);
}

$manifest = $source . '/pluggen.xml';

if (!is_file($manifest)) {
    fwrite(STDERR, "No manifest at $manifest\n");
    exit(1);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($manifest);

if ($xml === false) {
    fwrite(STDERR, "The manifest is not well-formed XML.\n");
    exit(1);
}

$version = trim((string) $xml->version);

// Joomla accepts looser version strings, but a release this project builds is
// expected to be semantic: the tag, the manifest and the file name all have to
// agree, and that is easier to check when the shape is fixed.
if (!preg_match('/^\d+\.\d+\.\d+(-[A-Za-z0-9.]+)?$/', $version)) {
    fwrite(STDERR, \sprintf("The manifest version \"%s\" is not of the form 1.2.3.\n", $version));
    exit(1);
}

$target = $root . '/build/com_pluggen-' . $version . '.zip';

// The version script.php refuses to go below, so the build cannot ship a
// library older than the component will accept.
$required = libraryMinimum($root . '/src/script.php');
$options  = getopt('', ['library::', 'no-library']);
$library  = null;

echo "Building com_pluggen {$version}
";

if (!isset($options['no-library'])) {
    $library = resolveLibrary($root, \is_string($options['library'] ?? null) ? $options['library'] : null, $required);
}

if (is_file($target)) {
    unlink($target);
}

$zip = new ZipArchive();

if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fwrite(STDERR, "Cannot create $target\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
);

$count = 0;

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $relative = str_replace('\\', '/', substr($file->getPathname(), \strlen($source) + 1));

    $zip->addFile($file->getPathname(), $relative);
    $count++;
}

if ($library !== null) {
    // script.php looks for it under library/ in the package it is installing from.
    $zip->addFile($library, 'library/' . basename($library));
    $count++;

    echo '  library/                 ' . basename($library) . "
";
}

$zip->close();

if ($library === null) {
    echo "
  No library bundled. A site without lib_yepr_gen will install this
"
        . "  component and then be unable to generate.
";
}

printf(
    "com_pluggen %s: %d files -> %s (%s bytes)\n",
    $version,
    $count,
    basename($target),
    number_format(filesize($target))
);

/**
 * The library version script.php refuses to go below.
 */
function libraryMinimum(string $script): string
{
    $source = (string) file_get_contents($script);

    preg_match("/LIBRARY_MINIMUM\s*=\s*'([^']+)'/", $source, $match);

    if (!isset($match[1])) {
        fwrite(STDERR, "script.php does not say which library version it needs.\n");
        exit(1);
    }

    return $match[1];
}

/**
 * The version in a library package's file name, or null when it has none.
 */
function libraryVersion(string $path): ?string
{
    return preg_match('/lib_yepr_gen-(\d+\.\d+\.\d+)\.zip$/', basename($path), $match) === 1
        ? $match[1]
        : null;
}

/**
 * Find the library package to bundle: the one given, the newest built locally,
 * or the released one.
 */
function resolveLibrary(string $root, ?string $given, string $required): string
{
    if ($given !== null && $given !== '') {
        if (!is_file($given)) {
            fwrite(STDERR, "No library package at {$given}.\n");
            exit(1);
        }

        return $given;
    }

    // A sibling checkout that has been built is the usual case while working on
    // both at once, and it is what should be shipped then - but only if it is
    // new enough. This used to take whichever local build sorted last, so
    // bumping LIBRARY_MINIMUM and rebuilding produced a package carrying a
    // library its own install script refuses: the zip installs, the library
    // does not, and the component lands on a site unable to generate.
    $local = [];

    foreach (glob($root . '/../generator-core/build/lib_yepr_gen-*.zip') ?: [] as $candidate) {
        $version = libraryVersion($candidate);

        if ($version !== null && version_compare($version, $required, '>=')) {
            $local[$version] = $candidate;
        }
    }

    if ($local !== []) {
        uksort($local, static fn (string $x, string $y): int => version_compare($x, $y));

        $newest = (string) end($local);

        echo '  using the locally built library: ' . basename($newest) . "\n";

        return $newest;
    }

    $url = 'https://github.com/HermanPeeren/generator-core/releases/download/v'
        . $required . '/lib_yepr_gen-' . $required . '.zip';
    $into = $root . '/build/tmp/lib_yepr_gen-' . $required . '.zip';

    if (!is_dir(\dirname($into)) && !mkdir(\dirname($into), 0755, true) && !is_dir(\dirname($into))) {
        fwrite(STDERR, 'Cannot create ' . \dirname($into) . "\n");
        exit(1);
    }

    echo '  fetching the released library ' . $required . "\n";

    $contents = @file_get_contents($url);

    if ($contents === false) {
        fwrite(STDERR, "Could not fetch {$url}.\nBuild the library locally, or pass --library=, or --no-library.\n");
        exit(1);
    }

    file_put_contents($into, $contents);

    return $into;
}
