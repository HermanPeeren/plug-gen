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
use Joomla\CMS\Router\Route;

/** @var \Yepr\Component\Pluggen\Administrator\View\Blueprint\HtmlView $this */

// Every fieldset a type bundle contributed, so new types appear here without
// this layout having to know about them.
$typeFieldsets = [];

foreach ($this->form->getFieldsets() as $name => $fieldset) {
    if (str_starts_with($name, 'type_') || str_starts_with($name, 'slots_')) {
        $typeFieldsets[$name] = $fieldset;
    }
}
?>
<form action="<?php echo Route::_('index.php?option=com_pluggen&layout=edit&id=' . (int) $this->item->id); ?>"
	method="post" name="adminForm" id="blueprint-form" class="form-validate">

	<?php echo HTMLHelper::_('uitab.startTabSet', 'blueprintTab', ['active' => 'general', 'recall' => true]); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'blueprintTab', 'general', Text::_('COM_PLUGGEN_TAB_GENERAL')); ?>
		<div class="row">
			<div class="col-lg-6">
				<?php echo $this->form->renderField('title'); ?>
				<?php echo $this->form->renderField('type_id'); ?>
				<?php echo $this->form->renderField('target'); ?>
				<?php echo $this->form->renderField('group'); ?>
				<?php echo $this->form->renderField('element'); ?>
				<?php echo $this->form->renderField('namespace'); ?>
				<?php echo $this->form->renderField('className'); ?>
				<?php echo $this->form->renderField('version'); ?>
			</div>
			<div class="col-lg-6">
				<?php echo $this->form->renderField('description'); ?>
				<?php echo $this->form->renderField('author_name'); ?>
				<?php echo $this->form->renderField('author_email'); ?>
				<?php echo $this->form->renderField('author_url'); ?>
				<?php echo $this->form->renderField('copyright'); ?>
				<?php echo $this->form->renderField('autoloadLanguage'); ?>
				<?php echo $this->form->renderField('services'); ?>
			</div>
		</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'blueprintTab', 'params', Text::_('COM_PLUGGEN_TAB_PARAMS')); ?>
		<?php echo $this->form->renderField('params'); ?>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php foreach ($typeFieldsets as $name => $fieldset) : ?>
		<?php echo HTMLHelper::_('uitab.addTab', 'blueprintTab', $name, Text::_($fieldset->label ?: $name)); ?>
			<?php echo $this->form->renderFieldset($name); ?>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>
	<?php endforeach; ?>

	<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
