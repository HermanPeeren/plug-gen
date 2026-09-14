<?php

/**
 * @package     Pluggen
 * @subpackage  Types.Workflow
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Types\Workflow;

use Yepr\Component\Pluggen\Administrator\Generator\Emitter\PhpEmitter as Php;
use Yepr\Component\Pluggen\Administrator\Generator\Emitter\XmlEmitter as Xml;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\PluginTypeInterface;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;
use Yepr\Component\Pluggen\Administrator\Generator\Output\ProtectedRegionMerger;
use Yepr\Component\Pluggen\Administrator\Generator\Template\Renderer;
use Yepr\Component\Pluggen\Administrator\Generator\Template\RendererAwareInterface;

/**
 * Workflow plugins: actions that run at a transition.
 *
 * The philosophy Joomla follows is that users trigger transitions and
 * transitions trigger actions. A workflow plugin supplies those actions. It
 * adds its own fields to the transition form, and when the transition happens
 * it reads the values back and does the work.
 *
 * Two details decide whether such a plugin works at all, and both are easy to
 * get wrong silently:
 *
 * - isSupported() returns false in WorkflowPluginTrait, so a plugin that does
 *   not override it is never asked to do anything.
 * - The fields in forms/action.xml live under the "options" field group, and
 *   their names become the keys of $transition->options.
 *
 * @since  0.1.0
 */
final class Definition implements PluginTypeInterface, RendererAwareInterface
{
    /**
     * The renderer for this bundle's templates.
     *
     * @var    Renderer
     * @since  0.1.0
     */
    private Renderer $renderer;

    /**
     * Set the template renderer.
     *
     * @param   Renderer  $renderer  The renderer for template files.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setRenderer(Renderer $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * The stable type id.
     *
     * @return  string  The id used in the model and in showon attributes.
     *
     * @since   0.1.0
     */
    public function id(): string
    {
        return 'workflow';
    }

    /**
     * The Joomla plugin group this type generates into.
     *
     * @return  string  The plugin group.
     *
     * @since   0.1.0
     */
    public function group(): string
    {
        return 'workflow';
    }

    /**
     * The untranslated label, used when no language string is available.
     *
     * @return  string  The label.
     *
     * @since   0.1.0
     */
    public function label(): string
    {
        return 'Workflow (transition actions)';
    }

    /**
     * The absolute path of this bundle.
     *
     * @return  string  The bundle folder.
     *
     * @since   0.1.0
     */
    public function path(): string
    {
        return __DIR__;
    }

    /**
     * The absolute path of the type-specific form.
     *
     * @return  string  The form file.
     *
     * @since   0.1.0
     */
    public function formPath(): string
    {
        return __DIR__ . \DIRECTORY_SEPARATOR . 'form.xml';
    }

    /**
     * The insertion points this type offers.
     *
     * @return  array<string, string>  Slot id => description.
     *
     * @since   0.1.0
     */
    public function slots(): array
    {
        $meta = json_decode((string) file_get_contents(__DIR__ . '/type.json'), true);

        return \is_array($meta['slots'] ?? null) ? $meta['slots'] : [];
    }

    /**
     * Type-specific validation, on top of the generic model validation.
     *
     * @param   PluginModel  $model  The model to check.
     *
     * @return  string[]  The problems found.
     *
     * @since   0.1.0
     */
    public function validate(PluginModel $model): array
    {
        $errors = [];

        $contexts = $this->contexts($model);

        if ($contexts === []) {
            $errors[] = 'A workflow plugin needs at least one supported context, such as "com_content.article".';
        }

        foreach ($contexts as $context) {
            if (!preg_match('/^com_[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/i', $context)) {
                $errors[] = \sprintf('"%s" is not a context; write it as component.item, for example com_content.article.', $context);
            }
        }

        $functionality = (string) $model->config('supportFunctionality', '');

        if ($functionality !== '' && !preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i', $functionality)) {
            $errors[] = 'The supported functionality looks like "core.state" or "core.featured".';
        }

        $actions = $this->actions($model);

        if ($actions === []) {
            $errors[] = 'A workflow plugin needs at least one transition action; that is what it contributes to a transition.';
        }

        $seen = [];

        foreach ($actions as $index => $action) {
            $name = (string) ($action['name'] ?? '');

            // The field name becomes the key in $transition->options, so it has
            // to be a plain name and it has to be unique.
            if (!preg_match('/^[a-z][a-z0-9_]*$/i', $name)) {
                $errors[] = \sprintf('Action %d needs a name such as "notify_author".', $index + 1);
            } elseif (isset($seen[$name])) {
                $errors[] = \sprintf('Two actions share the name "%s"; one would overwrite the other in the transition options.', $name);
            }

            $seen[$name] = true;
        }

        return $errors;
    }

    /**
     * The language keys a workflow plugin needs on top of the generic ones.
     *
     * Each action field is labelled in the transition form, so every action
     * needs a label key and, where one was given, a description key.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  array<string, string>  Language key => text.
     *
     * @since   0.1.0
     */
    public function languageKeys(PluginModel $model): array
    {
        $keys = [];

        foreach ($this->actions($model) as $action) {
            $name = (string) ($action['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $prefix = $this->actionKey($model, $name);
            $label  = (string) ($action['label'] ?? ucwords(str_replace('_', ' ', $name)));

            $keys[$prefix . '_LABEL'] = $label;

            if ((string) ($action['description'] ?? '') !== '') {
                $keys[$prefix . '_DESC'] = (string) $action['description'];
            }
        }

        return $keys;
    }

    /**
     * Contribute the plugin class and the transition action form.
     *
     * @param   PluginModel     $model  The plugin model.
     * @param   FileCollection  $files  The collection to add to.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function generate(PluginModel $model, FileCollection $files): void
    {
        $actions = [];

        foreach ($this->actions($model) as $action) {
            $action['key'] = $this->actionKey($model, (string) ($action['name'] ?? ''));
            $actions[]     = $action;
        }

        $files->add(
            'src/Extension/' . Php::identifier($model->className) . '.php',
            $this->renderer->render(__DIR__ . '/templates/Extension.php.tpl', [
                'm'        => $model,
                'contexts' => $this->contexts($model),
                'actions'  => $actions,
                'str'      => static fn(mixed $value): string => Php::string($value),
                'arr'      => static fn(array $value, int $indent = 0): string => Php::arrayLiteral($value, $indent),
                'id'       => static fn(string $value): string => Php::identifier($value),
                'region'   => static fn(string $slot, int $levels = 2): string
                    => ProtectedRegionMerger::region($slot, Php::indentBlock($model->slot($slot), $levels), $levels),
                'slot'     => static fn(string $slot): string => $model->slot($slot),
            ])
        );

        $files->add(
            'forms/action.xml',
            $this->renderer->render(__DIR__ . '/templates/ActionForm.xml.tpl', [
                'm'       => $model,
                'actions' => $actions,
                'attr'    => static fn(mixed $value): string => Xml::attr($value),
                'text'    => static fn(mixed $value): string => Xml::text($value),
            ])
        );
    }

    /**
     * The contexts this plugin supports, as a plain list.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  string[]  The contexts.
     *
     * @since   0.1.0
     */
    private function contexts(PluginModel $model): array
    {
        $contexts = $model->config('contexts', []);

        // A single context may arrive as a comma separated string from the form.
        if (\is_string($contexts)) {
            $contexts = explode(',', $contexts);
        }

        $clean = [];

        foreach ((array) $contexts as $context) {
            $context = trim((string) $context);

            if ($context !== '') {
                $clean[] = $context;
            }
        }

        return $clean;
    }

    /**
     * The transition actions from the model, as a plain list.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  array<int, array<string, mixed>>  The actions.
     *
     * @since   0.1.0
     */
    private function actions(PluginModel $model): array
    {
        $actions = [];

        foreach ((array) $model->config('actions', []) as $action) {
            if (\is_array($action) && ($action['name'] ?? '') !== '') {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * The language key prefix for one action field.
     *
     * Follows the core workflow plugins:
     * PLG_WORKFLOW_FEATURING_TRANSITION_ACTIONS_FEATURING_LABEL.
     *
     * @param   PluginModel  $model  The plugin model.
     * @param   string       $name   The action field name.
     *
     * @return  string  The key prefix.
     *
     * @since   0.1.0
     */
    private function actionKey(PluginModel $model, string $name): string
    {
        return $model->languagePrefix() . '_TRANSITION_ACTIONS_' . strtoupper($name);
    }
}
