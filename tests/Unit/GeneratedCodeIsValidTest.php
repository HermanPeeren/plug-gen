<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\Pipeline;
use Yepr\Component\Pluggen\Administrator\Generator\Target\PluginTarget;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * Generated PHP must parse and generated XML must be well formed.
 *
 * Cheap, and it catches the template typo that a golden test would otherwise
 * happily enshrine as the expected output.
 */
final class GeneratedCodeIsValidTest extends TestCase
{
    public function testEveryGeneratedFileIsSyntacticallyValid(): void
    {
        $files   = $this->generate();
        $checked = 0;

        foreach ($files as $path => $contents) {
            if (str_ends_with($path, '.php')) {
                $this->assertSame('', $this->phpSyntaxError($contents), 'PHP syntax error in ' . $path);
                $checked++;
            }

            if (str_ends_with($path, '.xml')) {
                $previous = libxml_use_internal_errors(true);
                $xml      = simplexml_load_string($contents);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);

                $this->assertTrue($xml !== false, 'Malformed XML in ' . $path);
                $checked++;
            }

            if (str_ends_with($path, '.ini')) {
                $parsed = @parse_ini_string($contents, false, INI_SCANNER_RAW);

                $this->assertTrue(\is_array($parsed), 'Unparsable language file: ' . $path);
                $this->assertNotEmpty($parsed, 'Empty language file: ' . $path);
                $checked++;
            }
        }

        $this->assertTrue($checked >= 5, 'Expected to check at least five generated files.');
    }

    /** Generation must be pure: the same model twice gives the same bytes. */
    public function testGenerationIsDeterministic(): void
    {
        $this->assertSame($this->generate()->all(), $this->generate()->all());
    }

    private function generate()
    {
        $json = (string) file_get_contents(PLUGGEN_TEST_ROOT . '/Fixtures/models/finder-recipes.json');

        return (new Pipeline())->run(PluginModel::fromJson($json), PluginTarget::default());
    }

    /** Returns '' when the code parses, otherwise the parser message. */
    private function phpSyntaxError(string $code): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pluggen_') . '.php';
        file_put_contents($file, $code);

        $output = [];
        $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);

        unlink($file);

        return $status === 0 ? '' : implode("\n", $output);
    }
}
