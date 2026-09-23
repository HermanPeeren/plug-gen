<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\Pipeline;
use Yepr\Component\Pluggen\Administrator\Generator\Target\PluginTarget;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The main test: a fixture model must generate exactly the committed output.
 *
 * This is the review surface for template changes - when this fails, read the
 * diff and decide whether it is an improvement or a regression, then rerun
 * tools/generate-fixture.php to accept it.
 */
final class GoldenOutputTest extends TestCase
{
    /**
     * Every fixture model, not a named one: dropping a new model and its golden
     * output into the fixtures folder is enough to have it checked from then on.
     */
    public function testEveryFixtureMatchesItsGoldenOutput(): void
    {
        $fixtures = $this->fixtures();

        $this->assertNotEmpty($fixtures, 'No fixture models found.');

        foreach ($fixtures as $fixture) {
            $files    = $this->generate($fixture);
            $expected = PLUGGEN_TEST_ROOT . '/Fixtures/expected/' . $fixture;

            $this->assertNotEmpty($files->paths(), 'The pipeline generated nothing for ' . $fixture . '.');

            foreach ($files as $path => $contents) {
                $goldenFile = $expected . '/' . $path;

                if (!is_file($goldenFile)) {
                    $this->fail('Generated a file with no golden counterpart: ' . $fixture . '/' . $path);
                }

                $golden = str_replace("\r\n", "\n", (string) file_get_contents($goldenFile));

                $this->assertSame(
                    $golden,
                    $contents,
                    'Generated output differs from the golden file: ' . $fixture . '/' . $path
                );
            }

            // And nothing in the golden set disappeared.
            foreach ($this->goldenPaths($expected) as $path) {
                $this->assertTrue(
                    $files->has($path),
                    'A golden file is no longer generated: ' . $fixture . '/' . $path
                );
            }
        }
    }

    /** @return string[] */
    private function fixtures(): array
    {
        $names = [];

        foreach (glob(PLUGGEN_TEST_ROOT . '/Fixtures/models/*.json') ?: [] as $file) {
            $names[] = basename($file, '.json');
        }

        sort($names, SORT_STRING);

        return $names;
    }

    public function testGeneratesTheExpectedFileSet(): void
    {
        $this->assertSame(
            [
                'language/en-GB/plg_finder_recipes.ini',
                'language/en-GB/plg_finder_recipes.sys.ini',
                'recipes.xml',
                'services/provider.php',
                'src/Extension/Recipes.php',
            ],
            $this->generate('finder-recipes')->paths()
        );
    }

    public function testSlotCodeReachesTheProtectedRegion(): void
    {
        $adapter = $this->generate('finder-recipes')->get('src/Extension/Recipes.php');

        $this->assertStringContainsString('// <pluggen id="finder.index.elements">', $adapter);
        $this->assertStringContainsString('$item->prep_time  = (int) $item->prep_time;', $adapter);
    }

    /**
     * The item context is the component's model name, not the plugin element.
     * com_recipes has a RecipeModel, so the context is com_recipes.recipe while
     * the plugin element is "recipes" - getting this wrong means the adapter
     * silently never hears about a save.
     */
    public function testItemContextUsesTheModelNameNotTheElement(): void
    {
        $adapter = $this->generate('finder-recipes')->get('src/Extension/Recipes.php');

        $this->assertStringContainsString("'com_recipes.recipe',", $adapter);
        $this->assertStringNotContainsString("'com_recipes.recipes'", $adapter);
    }

    private function generate(string $fixture)
    {
        $json = (string) file_get_contents(PLUGGEN_TEST_ROOT . '/Fixtures/models/' . $fixture . '.json');

        return (new Pipeline())->run(PluginModel::fromJson($json), PluginTarget::default());
    }

    /** @return string[] */
    private function goldenPaths(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $paths    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $paths[] = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
            }
        }

        sort($paths, SORT_STRING);

        return $paths;
    }
}
