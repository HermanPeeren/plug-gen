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
use Yepr\Component\Pluggen\Administrator\Model\BlueprintModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The edit form for one blueprint.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
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

        $document->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');

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

        $toolbar->cancel('blueprint.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
