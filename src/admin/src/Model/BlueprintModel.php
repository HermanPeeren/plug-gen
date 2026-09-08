<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;
use Yepr\Component\Pluggen\Administrator\Generator\Pipeline;
use Yepr\Component\Pluggen\Administrator\Service\ModelMapper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editing one blueprint.
 */
class BlueprintModel extends AdminModel
{
    protected $text_prefix = 'COM_PLUGGEN';

    private ?TypeRegistry $types = null;

    public function getTypes(): TypeRegistry
    {
        return $this->types ??= TypeRegistry::default();
    }

    /**
     * The general form, with every registered type's fieldsets appended.
     *
     * All type forms are loaded at once and their fields carry
     * showon="type_id:<id>", so Joomla shows only the fieldset belonging to the
     * selected type. No reload, no custom JavaScript.
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_pluggen.blueprint', 'blueprint', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        foreach ($this->getTypes()->all() as $type) {
            $formPath = $type->formPath();

            if ($formPath !== null && is_file($formPath)) {
                $form->loadFile($formPath, false);
            }
        }

        return $form;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_pluggen.edit.blueprint.data', []);

        if (empty($data)) {
            $item = $this->getItem();
            $data = $item;

            if (!empty($item->model)) {
                $mapper = new ModelMapper($this->getTypes());
                $flat   = $mapper->toForm((array) json_decode((string) $item->model, true));

                $data = array_merge((array) $item, $flat);
            }
        }

        $this->preprocessData('com_pluggen.blueprint', $data);

        return $data;
    }

    /**
     * Store the form as a model. The JSON is the record; the individual columns
     * exist only so the list view has something to show and filter on.
     */
    public function save($data)
    {
        $mapper = new ModelMapper($this->getTypes());
        $model  = $mapper->toModel($data);

        $data['type_id'] = (string) ($model['type']['id'] ?? '');
        $data['model']   = json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return parent::save($data);
    }

    /**
     * Validate a stored blueprint with the generator's own validator, so the user
     * sees the same problems the pipeline would refuse on.
     *
     * @return string[]
     */
    public function validateBlueprint(int $id): array
    {
        $item = $this->getItem($id);

        if (empty($item->model)) {
            return [Text::_('COM_PLUGGEN_ERR_NO_MODEL')];
        }

        $types = $this->getTypes();

        return (new ModelValidator($types))->validate(PluginModel::fromJson((string) $item->model));
    }

    /**
     * Run the pipeline for a stored blueprint.
     *
     * Returns the generated files in memory; writing them anywhere is the
     * controller's decision, and it never writes into the live plugins folder.
     */
    public function generate(int $id): FileCollection
    {
        $item = $this->getItem($id);

        if (empty($item->model)) {
            throw new \RuntimeException(Text::_('COM_PLUGGEN_ERR_NO_MODEL'));
        }

        $types    = $this->getTypes();
        $pipeline = new Pipeline($types, new ModelValidator($types));

        return $pipeline->run(PluginModel::fromJson((string) $item->model));
    }

    /**
     * The type list is built from the registry rather than hard-coded, so adding
     * a type folder is enough to make it selectable.
     */
    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        $options = $this->getTypes()->options();

        if ($options !== []) {
            $field = new \SimpleXMLElement(
                '<field name="type_id" type="list" label="COM_PLUGGEN_FIELD_TYPE_LABEL"'
                . ' description="COM_PLUGGEN_FIELD_TYPE_DESC" required="true" validate="options"/>'
            );

            foreach ($options as $id => $label) {
                $option = $field->addChild('option', htmlspecialchars($label, ENT_XML1, 'UTF-8'));
                $option->addAttribute('value', $id);
            }

            $form->setField($field, null, true);
        }

        parent::preprocessForm($form, $data, $group);
    }
}
