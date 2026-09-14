<?php

/**
 * Test bootstrap.
 *
 * Registers a PSR-4 autoloader for the generator core only. Nothing here loads
 * Joomla: if a test ever needs the CMS, that is a signal the code under test has
 * drifted out of the framework-agnostic core.
 */

declare(strict_types=1);

if (!\defined('PLUGGEN_TEST_ROOT')) {
    \define('PLUGGEN_TEST_ROOT', __DIR__);
}

// Where the administrator part of the component lives in the repository, in the
// layout Joomla itself uses. Tests that read source files rather than load
// classes go through this, so the next move touches one line.
if (!\defined('PLUGGEN_ADMIN_ROOT')) {
    \define('PLUGGEN_ADMIN_ROOT', \dirname(__DIR__) . '/src/administrator/components/com_pluggen');
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Yepr\\Component\\Pluggen\\Administrator\\' => PLUGGEN_ADMIN_ROOT . '/src/',
        'Yepr\\Component\\Pluggen\\Tests\\'         => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, \strlen($prefix));
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require_once $file;
        }

        return;
    }
});

// The component classes guard themselves with _JEXEC. Defining it is not the
// same as bootstrapping Joomla: it is a plain constant, and the classes these
// tests load still pull in nothing from the CMS.
if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}
