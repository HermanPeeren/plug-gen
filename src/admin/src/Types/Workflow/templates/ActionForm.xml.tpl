<?php
/**
 * Template for forms/action.xml, the transition action fields.
 *
 * Two things about this file are load-bearing. The fields live under the
 * "options" field group, which is what makes their values arrive as
 * $transition->options. And the fieldset name and label decide which tab of the
 * transition form they appear on: core's Publishing and Featuring plugins use
 * the shared "actions" tab, while the Notification plugin opens its own.
 *
 * @var \Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel $m
 * @var array    $actions  each with name, type, label, key, default, options
 * @var callable $attr     XML attribute value
 * @var callable $text     XML text content
 */

$fieldsetName  = (string) $m->config('fieldsetName', 'actions');
$fieldsetLabel = (string) $m->config('fieldsetLabel', 'COM_WORKFLOW_TRANSITION_ACTIONS_LABEL');
?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<!-- Transition actions for <?= $text($m->extensionName()) ?>. -->
<form>
	<fields name="options">
		<fieldset name="<?= $attr($fieldsetName) ?>" label="<?= $attr($fieldsetLabel) ?>">
<?php foreach ($actions as $action) : ?>
<?php
    $name    = (string) $action['name'];
    $type    = (string) ($action['type'] ?? 'text');
    $default = (string) ($action['default'] ?? '');
    $options = (array) ($action['options'] ?? []);
    $hasDesc = (string) ($action['description'] ?? '') !== '';
?>
			<field
				name="<?= $attr($name) ?>"
				type="<?= $attr($type) ?>"
				label="<?= $attr($action['key'] . '_LABEL') ?>"
<?php if ($hasDesc) : ?>
				description="<?= $attr($action['key'] . '_DESC') ?>"
<?php endif; ?>
<?php if ($type === 'radio') : ?>
				layout="joomla.form.field.radio.switcher"
<?php endif; ?>
				default="<?= $attr($default) ?>"
<?php if ($options === []) : ?>
				/>
<?php else : ?>
				>
<?php foreach ($options as $value => $label) : ?>
				<option value="<?= $attr($value) ?>"><?= $text($label) ?></option>
<?php endforeach; ?>
			</field>
<?php endif; ?>
<?php endforeach; ?>
		</fieldset>
	</fields>
</form>
