<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\View\Blueprint;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

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
     * @throws  \Exception  When the model reports errors.
     *
     * @since   0.1.0
     */
    public function display($tpl = null): void
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        if (\count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

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
        $this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');

        $isNew   = empty($this->item->id);
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

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
