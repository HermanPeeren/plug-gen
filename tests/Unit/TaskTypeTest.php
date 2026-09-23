<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\Pipeline;
use Yepr\Component\Pluggen\Administrator\Generator\Target\PluginTarget;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The task plugin type.
 *
 * A task plugin is wired almost entirely by convention - three events pointing
 * at TaskPluginTrait, and a TASKS_MAP saying where everything is - so these
 * tests pin the parts of that convention a typo would quietly break.
 */
final class TaskTypeTest extends TestCase
{
    public function testTheTaskTypeIsRegisteredAndSelectable(): void
    {
        $registry = TypeRegistry::default();

        $this->assertTrue($registry->has('task'));
        $this->assertTrue($registry->hasGroup('task'));
        $this->assertTrue(\in_array('task', array_keys(array_filter($registry->availability())), true));
    }

    public function testGeneratesAClassAndAFormPerRoutineWithParameters(): void
    {
        $this->assertSame(
            [
                'forms/cleanUp.xml',
                'forms/mailReport.xml',
                'language/en-GB/plg_task_recipes.ini',
                'language/en-GB/plg_task_recipes.sys.ini',
                'recipes.xml',
                'services/provider.php',
                'src/Extension/Recipes.php',
            ],
            $this->generate()->paths()
        );
    }

    /**
     * The manifest lists the folders that were generated, so the forms folder
     * appears without the manifest generator knowing what a task plugin is.
     */
    public function testManifestInstallsTheFormsFolder(): void
    {
        $manifest = $this->generate()->get('recipes.xml');

        $this->assertStringContainsString('<folder>forms</folder>', $manifest);
        $this->assertStringContainsString('<folder plugin="recipes">services</folder>', $manifest);
        $this->assertStringNotContainsString('<folder>language</folder>', $manifest);
    }

    /** The three events every task plugin hands to TaskPluginTrait. */
    public function testSubscribesTheThreeTaskEvents(): void
    {
        $class = $this->generate()->get('src/Extension/Recipes.php');

        $this->assertStringContainsString("'onTaskOptionsList'    => 'advertiseRoutines',", $class);
        $this->assertStringContainsString("'onExecuteTask'        => 'standardRoutineHandler',", $class);
        $this->assertStringContainsString("'onContentPrepareForm' => 'enhanceTaskItemForm',", $class);
    }

    /**
     * A routine is only reachable if its TASKS_MAP entry, its method and its
     * form agree. The language prefix must not repeat the plugin name either:
     * "recipes.cleanup" in plg_task_recipes is PLG_TASK_RECIPES_CLEANUP, not
     * PLG_TASK_RECIPES_RECIPES_CLEANUP.
     */
    public function testRoutineWiringAgreesWithItself(): void
    {
        $files = $this->generate();
        $class = $files->get('src/Extension/Recipes.php');

        $this->assertStringContainsString("'recipes.cleanup' => [", $class);
        $this->assertStringContainsString("'langConstPrefix' => 'PLG_TASK_RECIPES_CLEANUP',", $class);
        $this->assertStringContainsString("'method'          => 'cleanUp',", $class);
        $this->assertStringContainsString("'form'            => 'cleanUp',", $class);
        $this->assertStringNotContainsString('PLG_TASK_RECIPES_RECIPES', $class);

        $this->assertStringContainsString('private function cleanUp(ExecuteTaskEvent $event): int', $class);
        $this->assertTrue($files->has('forms/cleanUp.xml'));

        // The scheduler shows a routine by its language constant, so the keys
        // the class advertises have to exist in the language file.
        $language = $files->get('language/en-GB/plg_task_recipes.ini');

        $this->assertStringContainsString('PLG_TASK_RECIPES_CLEANUP_TITLE="Remove unpublished recipes"', $language);
        $this->assertStringContainsString('PLG_TASK_RECIPES_CLEANUP_DESC=', $language);
        $this->assertStringContainsString('PLG_TASK_RECIPES_CLEANUP_PARAM_MAX_AGE_DAYS=', $language);
    }

    /** A routine without parameters gets no form, and no form key in the map. */
    public function testRoutineWithoutParametersGetsNoForm(): void
    {
        $files = $this->pipeline()->run(PluginModel::fromArray($this->model([
            ['id' => 'recipes.ping', 'method' => 'ping'],
        ])), PluginTarget::default());

        $this->assertFalse($files->has('forms/ping.xml'));
        $this->assertStringNotContainsString("'form'", $files->get('src/Extension/Recipes.php'));
        $this->assertStringNotContainsString('<folder>forms</folder>', $files->get('recipes.xml'));
    }

    /** Traits come with their interface declared, as the handbook asks. */
    public function testSelectedServicesBringTraitAndInterfaceTogether(): void
    {
        $class = $this->generate()->get('src/Extension/Recipes.php');

        $this->assertStringContainsString('implements SubscriberInterface, DatabaseAwareInterface, MailerFactoryAwareInterface', $class);
        $this->assertStringContainsString('use DatabaseAwareTrait;', $class);
        $this->assertStringContainsString('use MailerFactoryAwareTrait;', $class);
        $this->assertStringContainsString('use Joomla\\Database\\DatabaseAwareInterface;', $class);
    }

    public function testRoutineCodeLandsInItsOwnProtectedRegion(): void
    {
        $class = $this->generate()->get('src/Extension/Recipes.php');

        $this->assertStringContainsString('// <pluggen id="task.routine.cleanUp">', $class);
        $this->assertStringContainsString('// <pluggen id="task.routine.mailReport">', $class);
        $this->assertStringContainsString('$mailer = $this->getMailerFactory()->createMailer();', $class);
    }

    public function testRoutinesMustBePresentAndDistinct(): void
    {
        $cases = [
            'no routines at all'   => [],
            'no id'                => [['method' => 'cleanUp']],
            'no method'            => [['id' => 'recipes.cleanup']],
            'duplicate id'         => [
                ['id' => 'recipes.cleanup', 'method' => 'one'],
                ['id' => 'recipes.cleanup', 'method' => 'two'],
            ],
            'duplicate method'     => [
                ['id' => 'recipes.one', 'method' => 'cleanUp'],
                ['id' => 'recipes.two', 'method' => 'cleanUp'],
            ],
            // TaskPluginTrait already defines these; a routine of the same name
            // would silently replace the plumbing it depends on.
            'trait method name'    => [['id' => 'recipes.x', 'method' => 'logTask']],
            'handler name clash'   => [['id' => 'recipes.x', 'method' => 'standardRoutineHandler']],
            'method with a dot'    => [['id' => 'recipes.x', 'method' => 'clean.up']],
        ];

        foreach ($cases as $label => $routines) {
            $model  = PluginModel::fromArray($this->model($routines));
            $errors = TypeRegistry::default()->get('task')->validate($model);

            $this->assertNotEmpty($errors, 'Expected a validation error for: ' . $label);
        }
    }

    public function testTheFixtureModelIsValid(): void
    {
        $json  = (string) file_get_contents(PLUGGEN_TEST_ROOT . '/Fixtures/models/task-recipes.json');
        $model = PluginModel::fromJson($json);

        $this->assertSame([], TypeRegistry::default()->get('task')->validate($model));
    }

    private function generate()
    {
        $json = (string) file_get_contents(PLUGGEN_TEST_ROOT . '/Fixtures/models/task-recipes.json');

        return $this->pipeline()->run(PluginModel::fromJson($json), PluginTarget::default());
    }

    private function pipeline(): Pipeline
    {
        return new Pipeline();
    }

    /** @param array<int, array<string, mixed>> $routines */
    private function model(array $routines): array
    {
        return [
            'modelVersion' => PluginModel::CURRENT_VERSION,
            'plugin'       => [
                'group'     => 'task',
                'name'         => 'Recipes',
                'orgNamespace' => 'Acme',
                'version'   => '1.0.0',
                'services'  => ['application' => true],
            ],
            'type'         => ['id' => 'task', 'config' => ['routines' => $routines]],
        ];
    }
}
