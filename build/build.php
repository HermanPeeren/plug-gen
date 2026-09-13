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

$zip->close();

printf(
    "com_pluggen %s: %d files -> %s (%s bytes)\n",
    $version,
    $count,
    basename($target),
    number_format(filesize($target))
);
