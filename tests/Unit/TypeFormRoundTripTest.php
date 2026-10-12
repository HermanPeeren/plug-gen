<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Service\ModelMapper;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * A blueprint must come back from the edit form the way it went in.
 *
 * The browser posts only the fields the form renders, so a field that goes
 * missing while the type forms are merged into the blueprint form is a value
 * that goes missing on the next save - silently, because the save itself
 * succeeds. That happened to a task routine's id, description and parameters on
 * Joomla 6.1.4: Form::load() looks at every descendant field of what it merges,
 * nested subforms included, and drops one whose name is also a top-level field
 * of the blueprint form ("id", "description", "params", "name").
 */
final class TypeFormRoundTripTest extends TestCase
{
    /** The fields a type hands to the form keep their nested subforms whole. */
    public function testTheRoutineKeepsAllItsFieldsInTheMergedForm(): void
    {
        $routine = $this->routineFields();

        $this->assertSame(['id', 'method', 'label', 'description', 'code', 'params'], array_keys($routine));
        $this->assertSame(['name', 'type', 'label', 'default'], $routine['params']);
    }

    /**
     * Form::load() and Form::loadFile() are the calls that drop nested fields
     * named like a top-level one, so the type forms must not go through them.
     */
    public function testTypeFormsAreNotMergedThroughFormLoad(): void
    {
        $source = (string) file_get_contents(\PLUGGEN_ADMIN_ROOT . '/src/Model/BlueprintModel.php');

        $this->assertSame(
            0,
            preg_match('/\$form->load(File)?\(/', $source),
            'BlueprintModel merges a form with Form::load(), which drops nested fields named like a top-level one.'
        );
    }

    public function testATaskBlueprintSurvivesTheFormRoundTrip(): void
    {
        $mapper = new ModelMapper(TypeRegistry::default());
        $stored = $this->fixture();
        $model  = $mapper->toModel($this->post($mapper->toForm($stored)));

        $routines = array_values($model['type']['config']['routines']);

        $this->assertCount(2, $routines);
        $this->assertSame('recipes.cleanup', $routines[0]['id']);
        $this->assertSame('cleanUp', $routines[0]['method']);
        $this->assertSame(
            $stored['type']['config']['routines'][0]['description'],
            $routines[0]['description']
        );
        $this->assertSame(
            ['max_age_days', 'max_items'],
            array_column(array_values($routines[0]['params']), 'name')
        );
        $this->assertSame('30', array_values($routines[0]['params'])[0]['default']);

        // And the generator still finds the routines: one without an id is
        // skipped, and a task plugin without routines is refused.
        $problems = TypeRegistry::default()->get('task')->validate(PluginModel::fromArray($model));

        $this->assertSame([], $problems);
    }

    /**
     * What the browser posts for a form filled with this data: only the routine
     * fields the merged form renders, under the keys a repeatable subform uses.
     */
    private function post(array $form): array
    {
        $fields = $this->routineFields();
        $rows   = [];

        foreach (array_values($form['config_task']['routines']) as $i => $routine) {
            $row = array_intersect_key($routine, $fields);

            if (isset($row['params'])) {
                $params = [];

                foreach (array_values($row['params']) as $j => $param) {
                    $params['params' . $j] = array_intersect_key($param, array_flip($fields['params']));
                }

                $row['params'] = $params;
            }

            $rows['routines' . $i] = $row;
        }

        $form['config_task']['routines'] = $rows;

        return $form;
    }

    /**
     * The routine's field names as the merged form defines them, with the
     * parameter row's field names under "params".
     *
     * @return  array<string, string|string[]>
     */
    private function routineFields(): array
    {
        $config = null;

        foreach (TypeRegistry::default()->formFields() as $field) {
            if ((string) $field['name'] === 'config_task') {
                $config = $field;
            }
        }

        $this->assertNotNull($config, 'The task type hands no config_task field to the form.');

        $fields = [];

        foreach ($config->xpath('form/field[@name="routines"]/form/field') ?: [] as $field) {
            $name          = (string) $field['name'];
            $fields[$name] = $name;
        }

        if (isset($fields['params'])) {
            $fields['params'] = array_map(
                'strval',
                $config->xpath('form/field[@name="routines"]/form/field[@name="params"]/form/field/@name') ?: []
            );
        }

        return $fields;
    }

    private function fixture(): array
    {
        return json_decode(
            (string) file_get_contents(\PLUGGEN_TEST_ROOT . '/Fixtures/models/task-recipes.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
