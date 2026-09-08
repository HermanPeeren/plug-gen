<?php

/**
 * Assembles the installable component package from src/.
 *
 * Entry names always use forward slashes, and the archive contains the manifest
 * at its root - a wrapper folder or a backslash separator both break the install
 * on a Linux host.
 */

declare(strict_types=1);

$root   = \dirname(__DIR__);
$source = $root . '/src';
$target = $root . '/build/com_pluggen.zip';

if (!is_dir($source)) {
    fwrite(STDERR, "No src/ directory found.\n");
    exit(1);
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

$zip->close();

printf("%d files -> %s (%s bytes)\n", $count, $target, number_format(filesize($target)));
