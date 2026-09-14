<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The generator core must stay framework-agnostic.
 *
 * Not a style rule: the moment a generator reaches for Factory::getApplication()
 * it stops being a pure model-to-text transformation, the unit tests need a CMS
 * bootstrap, and the suite stops being usable. Guard the boundary mechanically.
 *
 * Note that generated *templates* legitimately contain "use Joomla\..." lines -
 * that is the code being written, not code being run - so .tpl files are exempt.
 */
final class NoJoomlaDependencyTest extends TestCase
{
    public function testGeneratorCoreDoesNotImportJoomla(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(\PLUGGEN_ADMIN_ROOT . '/src/Generator') as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/^\s*use\s+Joomla\\\\/mi', $source)) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'These generator classes import Joomla: ' . implode(', ', $offenders));
    }

    public function testTypeBundlesDoNotImportJoomlaEither(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(\PLUGGEN_ADMIN_ROOT . '/src/Types') as $file) {
            if (str_ends_with($file, '.tpl')) {
                continue;
            }

            $source = (string) file_get_contents($file);

            if (preg_match('/^\s*use\s+Joomla\\\\/mi', $source)) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'These type definitions import Joomla: ' . implode(', ', $offenders));
    }

    /** @return string[] */
    private function phpFilesIn(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
