<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Pipeline;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The target must change the output, and in exactly one place.
 *
 * Container::lazy() arrived with joomla/di 3.1, which Joomla ships from 5.4
 * onwards; core plugins started using it in 6.1. On Joomla 5.0 to 5.3 the
 * method does not exist, so emitting it there is not a graceful degradation -
 * it is a fatal error the first time the plugin boots. The one thing these
 * tests really pin is that a Joomla 5 target never emits lazy().
 */
final class TargetTest extends TestCase
{
    public function testAJoomla6TargetRegistersThePluginLazily(): void
    {
        $provider = $this->provider('joomla-6.0');

        $this->assertStringContainsString('$container->lazy(Recipes::class, function (Container $container) {', $provider);
        $this->assertStringContainsString('            })', $provider);
    }

    public function testAJoomla5TargetUsesThePlainFactory(): void
    {
        $provider = $this->provider('joomla-5.0');

        $this->assertStringNotContainsString('lazy(', $provider);
        $this->assertStringContainsString("            PluginInterface::class,\n            function (Container \$container) {", $provider);
    }

    /** The target decides the wrapper and nothing else: same body, same services. */
    public function testOnlyTheRegistrationWrapperDiffers(): void
    {
        $six  = $this->provider('joomla-6.0');
        $five = $this->provider('joomla-5.0');

        foreach (
            [
                '$plugin = new Recipes(',
                "                    (array) PluginHelper::getPlugin('finder', 'recipes')",
                '$plugin->setApplication(Factory::getApplication());',
                '$plugin->setDatabase($container->get(DatabaseInterface::class));',
                '                return $plugin;',
            ] as $line
        ) {
            $this->assertStringContainsString($line, $six, 'Joomla 6 provider lost: ' . $line);
            $this->assertStringContainsString($line, $five, 'Joomla 5 provider lost: ' . $line);
        }
    }

    /** Both variants have to parse. A wrapper built from string pieces is easy to get wrong. */
    public function testBothTargetsProduceValidPhp(): void
    {
        foreach (['joomla-5.0', 'joomla-6.0'] as $target) {
            $file = tempnam(sys_get_temp_dir(), 'pluggen_') . '.php';
            file_put_contents($file, $this->provider($target));

            $output = [];
            $status = 0;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
            unlink($file);

            $this->assertSame(0, $status, $target . ' provider does not parse: ' . implode("\n", $output));
        }
    }

    /** An unknown or missing target must fall back to the oldest line, never the newest. */
    public function testAnUnreadableTargetIsTreatedAsTheOldestSupportedLine(): void
    {
        $this->assertSame(6, $this->model('joomla-6.0')->targetMajor());
        $this->assertSame(5, $this->model('joomla-5.0')->targetMajor());
        $this->assertSame(5, $this->model('')->targetMajor());
        $this->assertSame(5, $this->model('nonsense')->targetMajor());
    }

    /** The finder fixture, re-targeted. */
    private function model(string $target): PluginModel
    {
        $json = (string) file_get_contents(PLUGGEN_TEST_ROOT . '/Fixtures/models/finder-recipes.json');
        $data = json_decode($json, true);

        $data['target'] = $target;

        return PluginModel::fromArray($data);
    }

    /** The generated services/provider.php for one target. */
    private function provider(string $target): string
    {
        return Pipeline::default()->run($this->model($target))->all()['services/provider.php'];
    }
}
