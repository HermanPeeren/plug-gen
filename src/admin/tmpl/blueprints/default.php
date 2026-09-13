<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Yepr\Component\Pluggen\Administrator\View\Blueprints\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$canGen    = $this->getCurrentUser()->authorise('core.generate', 'com_pluggen');
?>
<form action="<?php echo Route::_('index.php?option=com_pluggen&view=blueprints'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<?php echo Text::_('COM_PLUGGEN_NO_BLUEPRINTS'); ?>
					</div>
				<?php else : ?>
					<table class="table" id="blueprintList">
						<caption class="visually-hidden"><?php echo Text::_('COM_PLUGGEN_MANAGER_BLUEPRINTS'); ?></caption>
						<thead>
							<tr>
								<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
								<th scope="col" class="w-1 text-center">
									<?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
								</th>
								<th scope="col">
									<?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
								</th>
								<th scope="col" class="w-15 d-none d-md-table-cell">
									<?php echo HTMLHelper::_('searchtools.sort', 'COM_PLUGGEN_FIELD_TYPE_LABEL', 'a.type_id', $listDirn, $listOrder); ?>
								</th>
								<th scope="col" class="w-15"><?php echo Text::_('COM_PLUGGEN_HEADING_GENERATE'); ?></th>
								<th scope="col" class="w-5 d-none d-md-table-cell">
									<?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
								</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($this->items as $i => $item) : ?>
							<tr class="row<?php echo $i % 2; ?>">
								<td class="text-center">
									<?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?>
								</td>
								<td class="text-center">
									<?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'blueprints.', true, 'cb'); ?>
								</td>
								<th scope="row">
									<a href="<?php echo Route::_('index.php?option=com_pluggen&task=blueprint.edit&id=' . (int) $item->id); ?>">
										<?php echo $this->escape($item->title); ?>
									</a>
								</th>
								<td class="d-none d-md-table-cell"><?php echo $this->escape($item->type_id); ?></td>
								<td>
									<?php if ($canGen) : ?>
										<?php $generateUrl = 'index.php?option=com_pluggen&task=blueprint.generate'
											. '&id=' . (int) $item->id
											. '&' . Session::getFormToken() . '=1'; ?>
										<a class="btn btn-sm btn-secondary" href="<?php echo Route::_($generateUrl); ?>">
											<span class="icon-download" aria-hidden="true"></span>
											<?php echo Text::_('COM_PLUGGEN_TOOLBAR_GENERATE'); ?>
										</a>
									<?php else : ?>
										<span class="text-muted"><?php echo Text::_('COM_PLUGGEN_NOT_AUTHORISED'); ?></span>
									<?php endif; ?>
								</td>
								<td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php echo $this->pagination->getListFooter(); ?>
				<?php endif; ?>

				<input type="hidden" name="task" value="">
				<input type="hidden" name="boxchecked" value="0">
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
