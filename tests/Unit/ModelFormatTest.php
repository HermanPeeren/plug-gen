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
 * Version 1.1 asks for a name written the way a person writes it, plus the
 * organisation, and works the other three out. A blueprint saved before that
 * change is still a valid description of a plugin, so it is read rather than
 * refused - and refusing it would be the difference between a user's saved work
 * opening and not.
 */
final class ModelFormatTest extends TestCase
{
    public function testAVersion10ModelIsReadIntoTheCurrentFormat(): void
    {
        $model = PluginModel::fromArray($this->version10());

        $this->assertSame(PluginModel::CURRENT_VERSION, $model->modelVersion);
        $this->assertSame('Recipes', $model->name);
        $this->assertSame('Acme', $model->orgNamespace);

        // And the derived values come back the same as the ones 1.0 stored.
        $this->assertSame('Recipes', $model->className());
        $this->assertSame('recipes', $model->elementName());
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

    /**
     * A 1.0 class name becomes a name again: the capitals were the word
     * boundaries, so they are where the spaces go back.
     */
    public function testAVersion10ClassNameIsSplitBackIntoWords(): void
    {
        $data = $this->version10();

        unset($data['plugin']['element'], $data['plugin']['namespace']);

        $data['plugin']['className'] = 'ArticleUpdateNotification';

        $model = PluginModel::fromArray($data);

        $this->assertSame('Article Update Notification', $model->name);
        $this->assertSame('ArticleUpdateNotification', $model->className());
        $this->assertSame('articleupdatenotification', $model->elementName());
        $this->assertSame('plg_finder_articleupdatenotification', $model->extensionName());
        $this->assertSame('PLG_FINDER_ARTICLEUPDATENOTIFICATION', $model->languagePrefix());
    }

    /** A run of capitals is one word, not one word per letter, in both directions. */
    public function testAnAcronymSurvivesTheRoundTrip(): void
    {
        $data = $this->version10();

        $data['plugin']['className'] = 'HTMLCleaner';

        $model = PluginModel::fromArray($data);

        $this->assertSame('HTML Cleaner', $model->name);
        $this->assertSame('HTMLCleaner', $model->className());
    }

    /**
     * The names a person would actually type, and the code they all become.
     *
     * The fixtures are all one word, so this is the only place the difference
     * between a name and the identifiers derived from it is visible at all.
     */
    public function testAnyReasonableSpellingOfANameGivesTheSameCode(): void
    {
        $written = [
            'Article Update Notification',
            'article update notification',
            'Article-update-notification',
            '  Article   Update   Notification  ',
        ];

        foreach ($written as $name) {
            $data = $this->version10();

            $data['modelVersion']   = PluginModel::CURRENT_VERSION;
            $data['plugin']['name'] = $name;

            $model = PluginModel::fromArray($data);

            $this->assertSame('ArticleUpdateNotification', $model->className(), 'from: ' . $name);
            $this->assertSame('articleupdatenotification', $model->elementName(), 'from: ' . $name);
        }
    }

    /** The package is named after the plugin and the version it is. */
    public function testThePackageNameCarriesTheVersion(): void
    {
        $data = $this->version10();

        $data['modelVersion']      = PluginModel::CURRENT_VERSION;
        $data['plugin']['name']    = 'Article Update Notification';
        $data['plugin']['version'] = '2.1.0';

        $this->assertSame(
            'plg_finder_articleupdatenotification-2.1.0',
            PluginModel::fromArray($data)->packageName()
        );
    }

    /** A prerelease version is a version, and survives into the name intact. */
    public function testAPrereleaseVersionIsKeptWholeInThePackageName(): void
    {
        $data = $this->version10();

        $data['modelVersion']      = PluginModel::CURRENT_VERSION;
        $data['plugin']['name']    = 'Recipes';
        $data['plugin']['version'] = '1.0.0-beta.2';

        $this->assertSame('plg_finder_recipes-1.0.0-beta.2', PluginModel::fromArray($data)->packageName());
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
