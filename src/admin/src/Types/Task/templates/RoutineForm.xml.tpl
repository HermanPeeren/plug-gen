<?php
/**
 * Template for one routine's parameter form.
 *
 * The fields defined here are added to the task item form by
 * enhanceTaskItemForm(), and reach the routine as
 * $event->getArgument('params')->fieldName.
 *
 * @var \Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel $m
 * @var array    $routine  id, method, label, langPrefix, formName, params
 * @var callable $attr     XML attribute value
 * @var callable $text     XML text content
 */

$params = (array) ($routine['params'] ?? []);
?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<!-- Parameters for the <?= $text($routine['id']) ?> routine. -->
<form>
	<fields name="params">
		<fieldset name="task_params">
<?php foreach ($params as $param) : ?>
<?php
    $name    = (string) ($param['name'] ?? '');
    $type    = (string) ($param['type'] ?? 'text');
    $label   = (string) ($param['label'] ?? '');
    $default = (string) ($param['default'] ?? '');
    $key     = $routine['langPrefix'] . '_PARAM_' . strtoupper($name);
?>
			<field
				name="<?= $attr($name) ?>"
				type="<?= $attr($type) ?>"
				label="<?= $attr($key) ?>"
<?php if ($default !== '') : ?>
				default="<?= $attr($default) ?>"
<?php endif; ?>
<?php if ($type === 'number') : ?>
				filter="integer"
<?php endif; ?>
				/>
<?php endforeach; ?>
		</fieldset>
	</fields>
</form>
