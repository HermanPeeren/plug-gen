<?php

/**
 * Regenerates the committed golden output for a fixture model.
 *
 * Run this deliberately, and read the diff: the golden files are the review
 * surface for every template change.
 *
 *   php tools/generate-fixture.php finder-recipes
 */

declare(strict_types=1);

require __DIR__ . '/../tests/bootstrap.php';

use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\Model\ValidationException;
use Yepr\Gen\Core\Pipeline;
use Yepr\Component\Pluggen\Administrator\Generator\Target\PluginTarget;

$name = $argv[1] ?? 'finder-recipes';

$modelFile = __DIR__ . '/../tests/Fixtures/models/' . basename($name) . '.json';
$outputDir = __DIR__ . '/../tests/Fixtures/expected/' . basename($name);

if (!is_file($modelFile)) {
    fwrite(STDERR, "No such fixture model: $modelFile\n");
    exit(1);
}

try {
    $model = PluginModel::fromJson((string) file_get_contents($modelFile));
    $files = (new Pipeline())->run($model, PluginTarget::default());
} catch (ValidationException $e) {
    fwrite(STDERR, "The model is not valid:\n - " . implode("\n - ", $e->getErrors()) . "\n");
    exit(1);
}

// Start from an empty directory, so removed files disappear from the golden set.
if (is_dir($outputDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($outputDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
} elseif (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Cannot create $outputDir\n");
    exit(1);
}

foreach ($files as $path => $contents) {
    $target    = $outputDir . '/' . $path;
    $directory = \dirname($target);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        fwrite(STDERR, "Cannot create $directory\n");
        exit(1);
    }

    file_put_contents($target, $contents);
    echo "  $path (" . \strlen($contents) . " bytes)\n";
}

echo \count($files) . " files written to tests/Fixtures/expected/" . basename($name) . "\n";
