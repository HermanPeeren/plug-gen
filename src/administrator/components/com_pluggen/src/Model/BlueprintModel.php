<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Model;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Yepr\Component\Pluggen\Administrator\Contract\ModelMapperAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\ModelValidatorAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\PipelineAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\TypeRegistryAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\UserStateAwareInterface;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\PluginGroups;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;
use Yepr\Component\Pluggen\Administrator\Generator\Pipeline;
use Yepr\Component\Pluggen\Administrator\Service\ModelMapper;
use Yepr\Component\Pluggen\Administrator\Service\UserStateInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editing one blueprint: the stored description of a plugin.
 *
 * Every collaborator arrives through PluggenMVCFactory. The class therefore has
 * no new keyword and no call to Joomla's Factory, which also means its behaviour
 * can be exercised by handing it doubles.
 *
 * @since  0.1.0
 */
class BlueprintModel extends AdminModel implements
    TypeRegistryAwareInterface,
    ModelMapperAwareInterface,
    PipelineAwareInterface,
    ModelValidatorAwareInterface,
    UserStateAwareInterface
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  0.1.0
     */
    protected $text_prefix = 'COM_PLUGGEN';

    /**
     * The registry of available plugin types.
     *
     * @var    TypeRegistry
     * @since  0.1.0
     */
    private TypeRegistry $types;

    /**
     * The mapper between form data and the stored model.
     *
     * @var    ModelMapper
     * @since  0.1.0
     */
    private ModelMapper $mapper;

    /**
     * The generation pipeline.
     *
     * @var    Pipeline
     * @since  0.1.0
     */
    private Pipeline $pipeline;

    /**
     * The model validator.
     *
     * @var    ModelValidator
     * @since  0.1.0
     */
    private ModelValidator $validator;

    /**
     * The per-user state store.
     *
     * @var    UserStateInterface
     * @since  0.1.0
     */
    private UserStateInterface $userState;

    /**
     * Set the plugin type registry.
     *
     * @param   TypeRegistry  $types  The registry of available plugin types.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setTypeRegistry(TypeRegistry $types): void
    {
        $this->types = $types;
    }

    /**
     * Set the form/model mapper.
     *
     * @param   ModelMapper  $mapper  The mapper between form data and the stored model.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setModelMapper(ModelMapper $mapper): void
    {
        $this->mapper = $mapper;
    }

    /**
     * Set the generation pipeline.
     *
     * @param   Pipeline  $pipeline  The pipeline that turns a model into a file set.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setPipeline(Pipeline $pipeline): void
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Set the model validator.
     *
     * @param   ModelValidator  $validator  The validator for plugin models.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setModelValidator(ModelValidator $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * Set the user state store.
     *
     * @param   UserStateInterface  $userState  The per-user state store.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setUserState(UserStateInterface $userState): void
    {
        $this->userState = $userState;
    }

    /**
     * Get the registry of plugin types.
     *
     * @return  TypeRegistry  The injected registry.
     *
     * @since   0.1.0
     */
    public function getTypes(): TypeRegistry
    {
        return $this->types;
    }

    /**
     * Build the edit form.
     *
     * The general fields come from forms/blueprint.xml; every registered type
     * then appends its own fieldsets. Their fields carry showon="type_id:<id>",
     * so Joomla shows only the fieldset belonging to the selected type - no
     * reload task and no custom JavaScript.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True to load the data from the model state.
     *
     * @return  Form|false  The form object, or false on failure.
     *
     * @since   0.1.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_pluggen.blueprint', 'blueprint', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Get the data for the edit form.
     *
     * After a failed save Joomla keeps the submitted data in the user state, so
     * that is tried first; otherwise the stored model is unfolded back into the
     * flat shape the form binds to.
     *
     * @return  array|object  The data for the form.
     *
     * @since   0.1.0
     */
    protected function loadFormData()
    {
        $data = $this->userState->get('com_pluggen.edit.blueprint.data', []);

        if (empty($data)) {
            $item = $this->getItem();
            $data = $item;

            if (!empty($item->model)) {
                $flat = $this->mapper->toForm((array) json_decode((string) $item->model, true));
                $data = array_merge((array) $item, $flat);
            }
        }

        $this->preprocessData('com_pluggen.blueprint', $data);

        return $data;
    }

    /**
     * Save a blueprint.
     *
     * The JSON model is the record; the separate columns exist only so the list
     * view has something to show and filter on.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success.
     *
     * @throws  \RuntimeException  When no generator is available for the chosen type.
     *
     * @since   0.1.0
     */
    public function save($data)
    {
        // The disabled options in the type dropdown are a hint to the browser and
        // nothing more: a crafted post can still name a group that has no bundle,
        // and Joomla's options rule would accept it because a disabled option is
        // still an option. So the rule is enforced here as well.
        //
        // This throws rather than using the deprecated setError(): the form only
        // offers selectable types, so reaching this line means the request was
        // forged or a type bundle vanished between loading and saving the form.
        // An error page is the right answer to that, and it is honest about the
        // request being wrong rather than the input being invalid.
        $typeId = (string) ($data['plugin_type'] ?? '');

        if ($typeId === '' || !$this->types->has($typeId)) {
            throw new \RuntimeException(Text::sprintf('COM_PLUGGEN_ERR_TYPE_NOT_AVAILABLE', $typeId));
        }

        $model = $this->mapper->toModel($data);

        $data['type_id'] = (string) ($model['type']['id'] ?? '');
        $data['model']   = json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return parent::save($data);
    }

    /**
     * Validate a stored blueprint with the generator's own validator.
     *
     * Uses the same validator the pipeline uses, so the user never sees a
     * blueprint pass here and fail there.
     *
     * @param   integer  $id  The blueprint id.
     *
     * @return  string[]  The problems found; empty when the blueprint is usable.
     *
     * @since   0.1.0
     */
    public function validateBlueprint(int $id): array
    {
        $item = $this->getItem($id);

        if (empty($item->model)) {
            return [Text::_('COM_PLUGGEN_ERR_NO_MODEL')];
        }

        return $this->validator->validate(PluginModel::fromJson((string) $item->model));
    }

    /**
     * Run the generation pipeline for a stored blueprint.
     *
     * Returns the files in memory. Writing them anywhere is the controller's
     * decision, and it never writes into the live plugins folder.
     *
     * @param   integer  $id  The blueprint id.
     *
     * @return  FileCollection  The generated files.
     *
     * @throws  \RuntimeException  When the blueprint has no model yet.
     * @throws  \Yepr\Component\Pluggen\Administrator\Generator\Model\ValidationException  When the model is not valid.
     *
     * @since   0.1.0
     */
    public function generate(int $id): FileCollection
    {
        $item = $this->getItem($id);

        if (empty($item->model)) {
            throw new \RuntimeException(Text::_('COM_PLUGGEN_ERR_NO_MODEL'));
        }

        return $this->pipeline->run(PluginModel::fromJson((string) $item->model));
    }

    /**
     * Fill the plugin type field with every known plugin group.
     *
     * Groups that have no type bundle are listed but disabled, so it is visible
     * which plugin types exist and which ones this generator can write yet. The
     * list comes from the registry, so dropping in a bundle is enough to make a
     * group selectable - no form file has to be edited.
     *
     * @param   Form    $form   The form to be altered.
     * @param   mixed   $data   The associated data for the form.
     * @param   string  $group  The plugin group to be executed.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        // The type fieldsets are loaded here rather than in getForm() because
        // Joomla binds the data straight after this method returns, and
        // Form::bindLevel() silently drops a scalar whose field does not exist
        // yet. Loading them afterwards left every type-specific field empty when
        // a saved blueprint was reopened - the fields were there, the values
        // were in the model, and nothing complained.
        foreach ($this->types->all() as $type) {
            $formPath = $type->formPath();

            if ($formPath !== null && is_file($formPath)) {
                $form->loadFile($formPath, false);
            }
        }

        $availability = $this->types->availability();

        if ($availability !== []) {
            $field = new \SimpleXMLElement(
                '<field name="plugin_type" type="list" label="COM_PLUGGEN_FIELD_TYPE_LABEL"'
                . ' description="COM_PLUGGEN_FIELD_TYPE_DESC" required="true" validate="options"/>'
            );

            foreach ($availability as $name => $type) {
                $label = $type?->label() ?? Text::sprintf(
                    'COM_PLUGGEN_TYPE_NOT_AVAILABLE',
                    PluginGroups::label($name)
                );

                $option = $field->addChild('option', htmlspecialchars($label, ENT_XML1, 'UTF-8'));
                $option->addAttribute('value', $name);

                if ($type === null) {
                    $option->addAttribute('disabled', 'true');
                }
            }

            $form->setField($field, null, true);
        }

        parent::preprocessForm($form, $data, $group);
    }
}
