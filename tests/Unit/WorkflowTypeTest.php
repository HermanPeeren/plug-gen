<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\Pipeline;
use Yepr\Component\Pluggen\Administrator\Generator\Target\PluginTarget;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The workflow plugin type.
 *
 * Two things decide whether a workflow plugin works at all, and both fail
 * silently when wrong: isSupported() returns false in WorkflowPluginTrait, so a
 * plugin that does not override it is never asked to do anything; and the action
 * fields must sit in the "options" field group, because that is what makes their
 * values arrive as $transition->options.
 */
final class WorkflowTypeTest extends TestCase
{
    public function testTheWorkflowTypeIsRegisteredAndSelectable(): void
    {
        $registry = TypeRegistry::default();

        $this->assertTrue($registry->has('workflow'));
        $this->assertSame('workflow', $registry->forGroup('workflow')->id());
    }

    public function testGeneratesAClassAndTheActionForm(): void
    {
        $this->assertSame(
            [
                'forms/action.xml',
                'language/en-GB/plg_workflow_recipes.ini',
                'language/en-GB/plg_workflow_recipes.sys.ini',
                'recipes.xml',
                'services/provider.php',
                'src/Extension/Recipes.php',
            ],
            $this->generate()->paths()
        );
    }

    /** Without this override the plugin is never asked to do anything. */
    public function testIsSupportedIsOverridden(): void
    {
        $class = $this->generate()->get('src/Extension/Recipes.php');

        $this->assertStringContainsString('protected function isSupported($context)', $class);
        $this->assertStringContainsString("private const CONTEXTS = [\n        'com_recipes.recipe',\n    ];", $class);
        $this->assertStringContainsString('$this->checkAllowedAndForbiddenlist($context)', $class);
        $this->assertStringContainsString("\$this->checkExtensionSupport(\$context, \$this->supportFunctionality)", $class);
        $this->assertStringContainsString("protected \$supportFunctionality = 'core.state';", $class);
    }

    /**
     * The field group is what carries the values into $transition->options, and
     * the fieldset decides which tab of the transition form they appear on.
     */
    public function testActionFieldsSitInTheOptionsGroup(): void
    {
        $form = $this->generate()->get('forms/action.xml');

        $this->assertStringContainsString('<fields name="options">', $form);
        $this->assertStringContainsString('<fieldset name="actions" label="COM_WORKFLOW_TRANSITION_ACTIONS_LABEL">', $form);
        $this->assertStringContainsString('name="target_category"', $form);
        $this->assertStringContainsString('label="PLG_WORKFLOW_RECIPES_TRANSITION_ACTIONS_TARGET_CATEGORY_LABEL"', $form);
    }

    /** Every action field is read back under the same name it was defined with. */
    public function testActionsAreReadBackFromTheTransitionOptions(): void
    {
        $class = $this->generate()->get('src/Extension/Recipes.php');

        $this->assertStringContainsString("\$target_category = \$transition->options->get('target_category');", $class);
        $this->assertStringContainsString("\$notify_author = \$transition->options->get('notify_author');", $class);
    }

    public function testLabelsForEveryActionExist(): void
    {
        $language = $this->generate()->get('language/en-GB/plg_workflow_recipes.ini');

        $this->assertStringContainsString('PLG_WORKFLOW_RECIPES_TRANSITION_ACTIONS_TARGET_CATEGORY_LABEL="Move to category"', $language);
        $this->assertStringContainsString('PLG_WORKFLOW_RECIPES_TRANSITION_ACTIONS_NOTIFY_AUTHOR_LABEL="Notify the author"', $language);
    }

    /** The before-transition handler is optional, and only then subscribed. */
    public function testBeforeTransitionIsOptional(): void
    {
        $withBefore = $this->generate()->get('src/Extension/Recipes.php');

        $this->assertStringContainsString("'onWorkflowBeforeTransition' => 'onWorkflowBeforeTransition',", $withBefore);
        $this->assertStringContainsString('$event->setStopTransition();', $withBefore);

        $without = (new Pipeline())->run(
            PluginModel::fromArray($this->model(['handleBeforeTransition' => false])),
            PluginTarget::default()
        )
            ->get('src/Extension/Recipes.php');

        $this->assertStringNotContainsString('onWorkflowBeforeTransition', $without);
        $this->assertStringContainsString('onWorkflowAfterTransition', $without);
    }

    public function testTheModelMustNameContextsAndActions(): void
    {
        $cases = [
            'no contexts'        => ['contexts' => []],
            'context without item' => ['contexts' => ['com_recipes']],
            'context not a component' => ['contexts' => ['recipes.recipe']],
            'no actions'         => ['actions' => []],
            'action without name' => ['actions' => [['type' => 'text']]],
            'duplicate action'   => ['actions' => [['name' => 'a'], ['name' => 'a']]],
            'odd functionality'  => ['supportFunctionality' => 'not a functionality'],
        ];

        foreach ($cases as $label => $override) {
            $model  = PluginModel::fromArray($this->model($override));
            $errors = TypeRegistry::default()->get('workflow')->validate($model);

            $this->assertNotEmpty($errors, 'Expected a validation error for: ' . $label);
        }
    }

    public function testTheFixtureModelIsValid(): void
    {
        $json  = (string) file_get_contents(PLUGGEN_TEST_ROOT . '/Fixtures/models/workflow-recipes.json');
        $model = PluginModel::fromJson($json);

        $this->assertSame([], TypeRegistry::default()->get('workflow')->validate($model));
    }

    private function generate()
    {
        $json = (string) file_get_contents(PLUGGEN_TEST_ROOT . '/Fixtures/models/workflow-recipes.json');

        return (new Pipeline())->run(PluginModel::fromJson($json), PluginTarget::default());
    }

    /** @param array<string, mixed> $override */
    private function model(array $override = []): array
    {
        $config = array_merge([
            'contexts'               => ['com_recipes.recipe'],
            'handleBeforeTransition' => true,
            'actions'                => [['name' => 'target_category', 'type' => 'text']],
        ], $override);

        return [
            'modelVersion' => PluginModel::CURRENT_VERSION,
            'plugin'       => [
                'group'     => 'workflow',
                'name'         => 'Recipes',
                'orgNamespace' => 'Acme',
                'version'   => '1.0.0',
                'services'  => ['application' => true],
            ],
            'type'         => ['id' => 'workflow', 'config' => $config],
        ];
    }
}
