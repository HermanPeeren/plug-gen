<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\View\Blueprints;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list of blueprints.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The blueprints to show.
     *
     * @var    object[]
     * @since  0.1.0
     */
    protected $items;

    /**
     * The pagination object.
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  0.1.0
     */
    protected $pagination;

    /**
     * The model state.
     *
     * @var    \Joomla\Registry\Registry
     * @since  0.1.0
     */
    protected $state;

    /**
     * The search tools form.
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  0.1.0
     */
    public $filterForm;

    /**
     * The active search filters.
     *
     * @var    array
     * @since  0.1.0
     */
    public $activeFilters;

    /**
     * Display the list.
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
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        if (\count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->filterForm
            ->addControlField('task')
            ->addControlField('boxchecked', '0');

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
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_PLUGGEN_MANAGER_BLUEPRINTS'), 'code');

        if ($user->authorise('core.create', 'com_pluggen')) {
            $toolbar->addNew('blueprint.add');
        }

        if ($user->authorise('core.edit.state', 'com_pluggen')) {
            $dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();
            $childBar->publish('blueprints.publish')->listCheck(true);
            $childBar->unpublish('blueprints.unpublish')->listCheck(true);
        }

        if ($user->authorise('core.delete', 'com_pluggen')) {
            $toolbar->delete('blueprints.delete', 'JTOOLBAR_DELETE')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($user->authorise('core.admin', 'com_pluggen')) {
            $toolbar->preferences('com_pluggen');
        }
    }
}
