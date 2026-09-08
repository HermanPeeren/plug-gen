<?php

/**
 * Zero-dependency test runner.
 *
 * Runs the same test classes PHPUnit would, for machines without composer
 * installed. `composer test` remains the real entry point.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Yepr\Component\Pluggen\Tests\TestCase;

if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    fwrite(STDERR, "PHPUnit is installed; run \"composer test\" instead.\n");
    exit(1);
}

$classes = [];

foreach (glob(__DIR__ . '/Unit/*Test.php') ?: [] as $file) {
    require_once $file;
    $classes[] = 'Yepr\\Component\\Pluggen\\Tests\\Unit\\' . basename($file, '.php');
}

$passed  = 0;
$failed  = 0;
$results = [];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        continue;
    }

    $short = substr($class, strrpos($class, '\\') + 1);

    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        $instance = new $class();

        try {
            $instance->$method();
            $passed++;
            $results[] = '  ok    ' . $short . '::' . $method;
        } catch (\Throwable $e) {
            $failed++;
            $results[] = '  FAIL  ' . $short . '::' . $method . "\n"
                . '        ' . str_replace("\n", "\n        ", $e->getMessage());
        }
    }
}

echo implode("\n", $results), "\n\n";
printf("%d passed, %d failed, %d assertions\n", $passed, $failed, TestCase::$assertions);

exit($failed === 0 ? 0 : 1);
