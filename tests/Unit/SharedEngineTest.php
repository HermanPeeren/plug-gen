<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The generation engine is the library's, and stays there: step 4.1.
 *
 * This component is where the engine was written. Stage 0 extracted it into
 * `generator-core` so that four components could share one copy, and left this
 * one running on the original - which is how the two spent a stage drifting
 * apart in small ways nobody would notice until they had to be reconciled.
 * They had not, as it turned out: the classes were behaviourally identical and
 * the golden files did not move by a byte. That is luck rather than a property,
 * and this file is the property.
 *
 * Three rules, and the first is the one that matters. A copy does not come back
 * by somebody deciding to fork the library; it comes back by somebody adding
 * `Output/FileCollection.php` because that is where it used to be, and every
 * test still passing because the class they wrote does what the library's does.
 *
 * @since  0.5.0
 */
final class SharedEngineTest extends TestCase
{
    /**
     * What the library owns, by the path this component used to keep it at.
     *
     * @var string[]
     */
    private const MOVED = [
        'Generator/Output/FileCollection.php',
        'Generator/Output/ZipWriter.php',
        'Generator/Output/ProtectedRegionMerger.php',
        'Generator/Emitter/PhpEmitter.php',
        'Generator/Emitter/XmlEmitter.php',
        'Generator/Emitter/IniEmitter.php',
        'Generator/Template/Renderer.php',
        'Generator/Generators/GeneratorInterface.php',
        'Generator/Model/ValidationException.php',
        'Generator/Pipeline.php',
    ];

    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * None of it is defined here any more.
     */
    public function testTheEngineIsNotReDefinedInThisComponent(): void
    {
        $back = [];

        foreach (self::MOVED as $path) {
            if (is_file(\PLUGGEN_ADMIN_ROOT . '/src/' . $path)) {
                $back[] = $path;
            }
        }

        $this->assertSame(
            [],
            $back,
            'These belong to yepr/generator-core and are defined here as well: ' . implode(', ', $back)
        );
    }

    /**
     * And the generators reach for the library's, not for something local.
     *
     * The guard is the point: a component that had quietly stopped using the
     * library would satisfy the rule above by having deleted the files, which
     * is the same evidence as having adopted it.
     */
    public function testTheGeneratorsImportTheLibrary(): void
    {
        $imports = 0;

        foreach ($this->sourceFiles() as $file) {
            $imports += preg_match_all('/^\s*use\s+Yepr\\\\Gen\\\\Core\\\\/mi', (string) file_get_contents($file));
        }

        $this->assertGreaterThan(
            10,
            $imports,
            'Almost nothing here imports the shared engine, so these rules are checking an empty room.'
        );
    }

    /**
     * The install script and composer ask for the same library.
     *
     * Two places name a version and only one is checked at run time.
     * `composer.json` decides what the tests run against; `LIBRARY_MINIMUM`
     * decides what an installed site is allowed to have, and what the build
     * bundles. Let them drift and a site is handed a library older than the
     * code that was tested - which is what 3.7 found in Meta-gen, on the eve of
     * its first release, in exactly this pair of files.
     */
    public function testTheDeclaredDependencyMatchesTheInstallScript(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        preg_match("/LIBRARY_MINIMUM\s*=\s*'([^']+)'/", $script, $minimum);

        $this->assertNotEmpty($minimum, 'script.php does not say which library version it needs.');

        $composer = json_decode(
            (string) file_get_contents($this->root() . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $constraint = $composer['require']['yepr/generator-core'] ?? '';

        $this->assertSame(
            '^' . implode('.', \array_slice(explode('.', $minimum[1]), 0, 2)),
            $constraint,
            'composer.json asks for ' . $constraint . ' but script.php insists on ' . $minimum[1] . '.'
        );
    }

    /**
     * The manifest runs that script, or none of the above reaches a site.
     *
     * A component that needs a library and does not ship one installs cleanly
     * and fatals on the first screen that generates anything. There is no
     * Joomla mechanism for declaring the dependency; the script is the whole of
     * it, and a manifest that does not name it is the same as not having one.
     */
    public function testTheManifestRunsTheInstallScript(): void
    {
        $manifest = simplexml_load_file($this->root() . '/src/pluggen.xml');

        $this->assertNotFalse($manifest, 'The manifest is not valid XML.');
        $this->assertSame('script.php', trim((string) $manifest->scriptfile));
        $this->assertFileExists($this->root() . '/src/script.php');
    }

    /**
     * And the build puts a library in the package for it to find.
     *
     * Read out of the build script rather than checked by building, because a
     * build may reach the network and this has to fail on the commit that
     * breaks it.
     */
    public function testTheBuildBundlesALibraryTheScriptWillAccept(): void
    {
        $build = (string) file_get_contents($this->root() . '/build/build.php');

        $this->assertStringContainsString(
            '\'library/\' . basename($library)',
            $build,
            'The build does not put the library where script.php looks for it.'
        );

        $this->assertStringContainsString(
            'version_compare($version, $required,',
            $build,
            'The build picks a local library without checking it is new enough.'
        );
    }

    /**
     * @return  string[]  Every PHP file in the component's own source.
     */
    private function sourceFiles(): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\PLUGGEN_ADMIN_ROOT . '/src', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
