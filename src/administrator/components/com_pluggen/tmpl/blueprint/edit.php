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

// Two tabs hold every plugin type, not two tabs per type. Each type contributes
// one settings block and one custom-code block, both single subforms carrying
// showon="plugin_type:<id>", so the browser shows the pair belonging to the
// selected type and hides the rest. With a dozen types installed this is still
// four tabs.
$settings = [];
$code     = [];

foreach ($this->form->getFieldset() as $field) {
    if (str_starts_with($field->fieldname, 'config_')) {
        $settings[] = $field->fieldname;
    }

    if (str_starts_with($field->fieldname, 'slots_')) {
        $code[] = $field->fieldname;
    }
}
?>
<?php
// The starting state of the field descriptions comes from the component
// options and is rendered as a class, so the page never shows descriptions the
// user asked not to see and then hides them once a script has loaded. The data
// attribute is what descriptions.js looks for.
$descriptionClass = $this->showDescriptions ? '' : ' pluggen-descriptions-hidden';
?>
<form action="<?php echo Route::_('index.php?option=com_pluggen&layout=edit&id=' . (int) $this->item->id); ?>"
	method="post" name="adminForm" id="blueprint-form"
	class="form-validate<?php echo $descriptionClass; ?>" data-pluggen-descriptions="">

	<?php echo HTMLHelper::_('uitab.startTabSet', 'blueprintTab', ['active' => 'general', 'recall' => true]); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'blueprintTab', 'general', Text::_('COM_PLUGGEN_TAB_GENERAL')); ?>
		<div class="row">
			<div class="col-lg-6">
				<?php echo $this->form->renderField('name'); ?>
				<?php echo $this->form->renderField('plugin_type'); ?>
				<?php echo $this->form->renderField('target'); ?>
				<?php echo $this->form->renderField('org_namespace'); ?>
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
		<?php echo $this->form->renderField('custom_services'); ?>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'blueprintTab', 'params', Text::_('COM_PLUGGEN_TAB_PARAMS')); ?>
		<?php echo $this->form->renderField('params'); ?>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'blueprintTab', 'settings', Text::_('COM_PLUGGEN_TAB_SETTINGS')); ?>
		<?php foreach ($settings as $field) : ?>
			<?php echo $this->form->renderField($field); ?>
		<?php endforeach; ?>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'blueprintTab', 'code', Text::_('COM_PLUGGEN_TAB_CODE')); ?>
		<?php foreach ($code as $field) : ?>
			<?php echo $this->form->renderField($field); ?>
		<?php endforeach; ?>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
