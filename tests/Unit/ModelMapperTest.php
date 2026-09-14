<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Service\ModelMapper;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The translation between the edit form and the stored model.
 *
 * Since the form asks for the plugin type only, the group has to be derived on
 * the way in and must still be present in the stored model - a generator reading
 * the JSON should never have to resolve a type to learn where the plugin belongs.
 */
final class ModelMapperTest extends TestCase
{
    public function testGroupIsDerivedFromTheSelectedType(): void
    {
        $model = $this->mapper()->toModel($this->formData());

        $this->assertSame('finder', $model['type']['id']);
        $this->assertSame('finder', $model['plugin']['group']);
    }

    /** A blueprint for a group whose bundle is gone still records its group. */
    public function testUnknownTypeStillRecordsAGroup(): void
    {
        $model = $this->mapper()->toModel(['plugin_type' => 'workflow']);

        $this->assertSame('workflow', $model['plugin']['group']);
        $this->assertSame('workflow', $model['type']['id']);
    }

    public function testTypeConfigAndSlotsComeFromTheirOwnGroups(): void
    {
        $model = $this->mapper()->toModel($this->formData());

        $this->assertSame('recipe', $model['type']['config']['itemName']);
        $this->assertSame('$item->prep_time = 1;', $model['slots']['finder.index.elements']['code']);
    }

    /** A field name that is not a declared slot must not reach the model. */
    public function testUnknownSlotFieldsAreDropped(): void
    {
        $data = $this->formData();

        $data['slots_finder']['not_a_slot'] = 'echo "nope";';

        $model = $this->mapper()->toModel($data);

        $this->assertFalse(\array_key_exists('not_a_slot', $model['slots']));
    }

    public function testRoundTripKeepsTheUserOnTheSameForm(): void
    {
        $mapper = $this->mapper();
        $form   = $mapper->toForm($mapper->toModel($this->formData()));

        $this->assertSame('finder', $form['plugin_type']);
        $this->assertSame('Recipes', $form['name']);
        $this->assertSame('Acme', $form['org_namespace']);
        $this->assertSame('Recipes', $form['config_finder']['context']);
        $this->assertSame('$item->prep_time = 1;', $form['slots_finder']['finder_index_elements']);

        // The form has no group field any more; offering one would be a second
        // way to answer a question that is already answered.
        $this->assertFalse(\array_key_exists('group', $form));
    }

    /** The name is stored as written, tidied of surrounding space only. */
    public function testTheNameIsStoredAsWritten(): void
    {
        $data = $this->formData();

        $data['name'] = '  Article update notification  ';

        $this->assertSame(
            'Article update notification',
            $this->mapper()->toModel($data)['plugin']['name']
        );
    }

    /** An empty repeatable row is the user opening one and thinking better of it. */
    public function testBlankCustomServiceRowsAreDropped(): void
    {
        $services = $this->mapper()->toModel($this->formData())['plugin']['customServices'];

        $this->assertCount(1, $services);
        $this->assertSame('cache', $services[0]['name']);
        $this->assertSame('$container->get(Some::class)', $services[0]['expression']);
    }

    public function testSelectedServicesBecomeAMap(): void
    {
        $model = $this->mapper()->toModel($this->formData());

        $this->assertSame(['application' => true, 'database' => true], $model['plugin']['services']);
    }

    private function mapper(): ModelMapper
    {
        return new ModelMapper(TypeRegistry::default());
    }

    private function formData(): array
    {
        return [
            'plugin_type'  => 'finder',
            'name'         => 'Recipes',
            'org_namespace' => 'Acme',
            'version'      => '1.0.0',
            'services'     => ['application', 'database'],
            'custom_services' => [
                ['name' => 'cache', 'use' => 'Some\\Class', 'expression' => '$container->get(Some::class)'],
                ['name' => '', 'use' => '', 'expression' => ''],
            ],
            'config_finder' => [
                'context'   => 'Recipes',
                'extension' => 'com_recipes',
                'itemName'  => 'recipe',
                'table'     => '#__recipes',
            ],
            'slots_finder' => [
                'finder_index_elements' => '$item->prep_time = 1;',
            ],
        ];
    }
}
