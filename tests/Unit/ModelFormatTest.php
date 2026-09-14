<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The stored format, and what happens to models written in the older one.
 *
 * Version 1.0 asked for the element, the full namespace and the class name.
 * Version 1.1 asks for the system name and the organisation, and works the
 * other three out. A blueprint saved before that change is still a valid
 * description of a plugin, so it is read rather than refused - and refusing it
 * would be the difference between a user's saved work opening and not.
 */
final class ModelFormatTest extends TestCase
{
    public function testAVersion10ModelIsReadIntoTheCurrentFormat(): void
    {
        $model = PluginModel::fromArray($this->version10());

        $this->assertSame(PluginModel::CURRENT_VERSION, $model->modelVersion);
        $this->assertSame('recipes', $model->systemName);
        $this->assertSame('Acme', $model->orgNamespace);

        // And the derived values come back the same as the ones 1.0 stored.
        $this->assertSame('Recipes', $model->className());
        $this->assertSame('Acme\\Plugin\\Finder\\Recipes', $model->rootNamespace());
        $this->assertSame('plg_finder_recipes', $model->extensionName());
    }

    /** Upgraded on the way in means valid, not merely readable. */
    public function testAnUpgradedModelPassesValidation(): void
    {
        $this->assertSame([], (new ModelValidator())->validate(PluginModel::fromArray($this->version10())));
    }

    /** A namespace that never followed the convention still yields an organisation. */
    public function testAnUnconventionalNamespaceFallsBackToItsFirstSegment(): void
    {
        $data = $this->version10();

        $data['plugin']['namespace'] = 'Acme\\Finder\\Recipes';

        $this->assertSame('Acme', PluginModel::fromArray($data)->orgNamespace);
    }

    /** A version this code does not know is left alone, so the validator can refuse it. */
    public function testAnUnknownVersionIsNotSilentlyUpgraded(): void
    {
        $data = $this->version10();

        $data['modelVersion'] = '2.0';

        $model = PluginModel::fromArray($data);

        $this->assertSame('2.0', $model->modelVersion);
        $this->assertNotEmpty((new ModelValidator())->validate($model));
    }

    /** Saving reads back as what was saved. */
    public function testTheCurrentFormatRoundTrips(): void
    {
        $model = PluginModel::fromArray($this->version10());
        $again = PluginModel::fromJson($model->toJson());

        $this->assertSame($model->toArray(), $again->toArray());
    }

    private function version10(): array
    {
        return [
            'modelVersion' => '1.0',
            'target'       => 'joomla-6.0',
            'plugin'       => [
                'group'     => 'finder',
                'element'   => 'recipes',
                'namespace' => 'Acme\\Plugin\\Finder\\Recipes',
                'className' => 'Recipes',
                'version'   => '1.0.0',
            ],
            'type'         => ['id' => 'finder', 'config' => []],
        ];
    }
}
