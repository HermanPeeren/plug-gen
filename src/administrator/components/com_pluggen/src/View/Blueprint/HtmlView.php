<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\View\Blueprint;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use Yepr\Component\Pluggen\Administrator\Contract\ComponentParamsAwareInterface;
use Yepr\Component\Pluggen\Administrator\Model\BlueprintModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The edit form for one blueprint.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView implements ComponentParamsAwareInterface
{
    /**
     * The edit form.
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  0.1.0
     */
    protected $form;

    /**
     * The blueprint being edited.
     *
     * @var    object
     * @since  0.1.0
     */
    protected $item;

    /**
     * The model state.
     *
     * @var    \Joomla\Registry\Registry
     * @since  0.1.0
     */
    protected $state;

    /**
     * Whether the field descriptions start out visible.
     *
     * Read by the layout, which puts the starting state on the form as a class.
     *
     * @var    boolean
     * @since  0.4.4
     */
    protected $showDescriptions = true;

    /**
     * The component's own options.
     *
     * @var    Registry
     * @since  0.4.4
     */
    private Registry $params;

    /**
     * Set the component options.
     *
     * @param   Registry  $params  The component options.
     *
     * @return  void
     *
     * @since   0.4.4
     */
    public function setComponentParams(Registry $params): void
    {
        $this->params = $params;
    }

    /**
     * Display the edit form.
     *
     * @param   ?string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @throws  \UnexpectedValueException  When the view was given the wrong model.
     *
     * @since   0.1.0
     */
    public function display($tpl = null): void
    {
        // Asked of the model directly; AbstractView::get() and the getErrors()
        // collection behind the usual error block are both deprecated for
        // removal in Joomla 7.
        $model = $this->getModel();

        if (!$model instanceof BlueprintModel) {
            throw new \UnexpectedValueException('The blueprint view needs the blueprint model.');
        }

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        $this->showDescriptions = (bool) $this->params->get('show_descriptions', 1);

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    protected function addToolbar(): void
    {
        $document = $this->getDocument();

        // Only an HTML document has a toolbar and an asset manager, and it only
        // hands out a toolbar when it has one to give.
        if (!$document instanceof HtmlDocument) {
            return;
        }

        $document->getWebAssetManager()
            ->useScript('keepalive')
            ->useScript('form.validate')
            ->useStyle('com_pluggen.admin')
            ->useScript('com_pluggen.descriptions');

        $toolbar = $document->getToolbar();

        if ($toolbar === null) {
            return;
        }

        $isNew = empty($this->item->id);
        $user  = $this->getCurrentUser();

        ToolbarHelper::title(
            $isNew ? Text::_('COM_PLUGGEN_MANAGER_BLUEPRINT_NEW') : Text::_('COM_PLUGGEN_MANAGER_BLUEPRINT_EDIT'),
            'code'
        );

        $toolbar->apply('blueprint.apply');
        $toolbar->save('blueprint.save');

        // Generating is its own permission: it produces executable PHP.
        if (!$isNew && $user->authorise('core.generate', 'com_pluggen')) {
            $toolbar->standardButton('download', Text::_('COM_PLUGGEN_TOOLBAR_GENERATE'), 'blueprint.generate')
                ->icon('icon-download');
        }

        // No task: the script bound to the class does the work, the way core's
        // own inline help button does. The class is set here rather than left to
        // the button's name, so nothing depends on how core happens to build it.
        $toolbar->basicButton('descriptions')
            ->text(Text::_('COM_PLUGGEN_TOOLBAR_DESCRIPTIONS'))
            ->icon('icon-info-circle')
            ->buttonClass('btn btn-info button-descriptions');

        $toolbar->cancel('blueprint.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
