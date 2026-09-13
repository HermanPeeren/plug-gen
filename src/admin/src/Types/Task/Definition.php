<?php

/**
 * @package     Pluggen
 * @subpackage  Types.Task
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Types\Task;

use Yepr\Component\Pluggen\Administrator\Generator\Emitter\PhpEmitter as Php;
use Yepr\Component\Pluggen\Administrator\Generator\Emitter\XmlEmitter as Xml;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\PluginTypeInterface;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;
use Yepr\Component\Pluggen\Administrator\Generator\Output\ProtectedRegionMerger;
use Yepr\Component\Pluggen\Administrator\Generator\Template\Renderer;
use Yepr\Component\Pluggen\Administrator\Generator\Template\RendererAwareInterface;

/**
 * Scheduled task plugins.
 *
 * A task plugin offers the scheduler one or more routines. The wiring is almost
 * entirely convention: three events point at methods of TaskPluginTrait, and a
 * TASKS_MAP constant tells those methods where to find everything. What is left
 * for a human to write is the body of each routine - which is exactly the part
 * this generator leaves open.
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
        return 'task';
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
        return 'task';
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
        return 'Task (scheduled task)';
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
     * These are the plugin-wide ones. The body of each routine is a slot too,
     * but it belongs to the routine rather than to the plugin, so it lives in
     * the routine itself rather than in this list.
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
        $errors   = [];
        $routines = $this->routines($model);

        if ($routines === []) {
            return ['A task plugin needs at least one routine; that is what the scheduler can run.'];
        }

        $seenIds     = [];
        $seenMethods = [];

        foreach ($routines as $index => $routine) {
            $position = $index + 1;
            $id       = (string) ($routine['id'] ?? '');
            $method   = (string) ($routine['method'] ?? '');

            // The routine id is stored in #__scheduler_tasks and is what the
            // scheduler matches a saved task against, so it has to be stable
            // and plain. Core writes them as "plugin.routine".
            if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/i', $id)) {
                $errors[] = \sprintf('Routine %d needs an id such as "recipes.cleanup".', $position);
            } elseif (isset($seenIds[$id])) {
                $errors[] = \sprintf('Two routines share the id "%s"; the scheduler could not tell them apart.', $id);
            }

            $seenIds[$id] = true;

            if (!preg_match('/^[a-z][A-Za-z0-9_]*$/', $method)) {
                $errors[] = \sprintf('Routine %d needs a method name such as "cleanUp".', $position);
            } elseif (isset($seenMethods[$method])) {
                $errors[] = \sprintf('Two routines share the method "%s"; one would overwrite the other.', $method);
            } elseif (\in_array($method, self::RESERVED_METHODS, true)) {
                $errors[] = \sprintf(
                    'Routine %d cannot use the method name "%s": TaskPluginTrait already defines it.',
                    $position,
                    $method
                );
            }

            $seenMethods[$method] = true;

            foreach ((array) ($routine['params'] ?? []) as $paramIndex => $param) {
                $name = \is_array($param) ? (string) ($param['name'] ?? '') : '';

                if (!preg_match('/^[a-z][a-z0-9_]*$/i', $name)) {
                    $errors[] = \sprintf(
                        'Parameter %d of routine %d has no valid name.',
                        $paramIndex + 1,
                        $position
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * The language keys a task plugin needs on top of the generic ones.
     *
     * The scheduler shows a routine by its language constant: advertiseRoutines()
     * hands the langConstPrefix to the task form, which appends _TITLE and _DESC.
     * Without these keys a routine shows up as a raw constant.
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

        foreach ($this->routines($model) as $routine) {
            $prefix = $this->langPrefix($model, $routine);

            $keys[$prefix . '_TITLE'] = (string) ($routine['label'] ?? $this->titleFrom($routine));
            $keys[$prefix . '_DESC']  = (string) ($routine['description'] ?? '');

            // Each routine parameter is labelled by a key of its own, in the
            // routine's form. Generating the form without the keys would leave
            // raw constants on the task edit screen.
            foreach ((array) ($routine['params'] ?? []) as $param) {
                $name = \is_array($param) ? (string) ($param['name'] ?? '') : '';

                if ($name === '') {
                    continue;
                }

                $label = (string) ($param['label'] ?? ucwords(str_replace('_', ' ', $name)));

                $keys[$prefix . '_PARAM_' . strtoupper($name)] = $label;
            }
        }

        return $keys;
    }

    /**
     * Contribute the plugin class and a parameter form per routine that has one.
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
        $routines = [];

        foreach ($this->routines($model) as $routine) {
            $routine['langPrefix'] = $this->langPrefix($model, $routine);
            $routine['formName']   = $this->formName($routine);
            $routines[]            = $routine;
        }

        $files->add(
            'src/Extension/' . Php::identifier($model->className) . '.php',
            $this->renderer->render(__DIR__ . '/templates/Extension.php.tpl', [
                'm'        => $model,
                'routines' => $routines,
                'str'      => static fn(mixed $value): string => Php::string($value),
                'id'       => static fn(string $value): string => Php::identifier($value),
                'region'   => static fn(string $slot, string $code = '', int $levels = 2): string
                    => ProtectedRegionMerger::region($slot, Php::indentBlock($code, $levels), $levels),
                'slot'     => static fn(string $slot): string => $model->slot($slot),
            ])
        );

        foreach ($routines as $routine) {
            if ((array) ($routine['params'] ?? []) === []) {
                continue;
            }

            $files->add(
                'forms/' . $routine['formName'] . '.xml',
                $this->renderer->render(__DIR__ . '/templates/RoutineForm.xml.tpl', [
                    'm'       => $model,
                    'routine' => $routine,
                    'attr'    => static fn(mixed $value): string => Xml::attr($value),
                    'text'    => static fn(mixed $value): string => Xml::text($value),
                ])
            );
        }
    }

    /**
     * Method names TaskPluginTrait already occupies.
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const RESERVED_METHODS = [
        'startRoutine',
        'endRoutine',
        'enhanceTaskItemForm',
        'advertiseRoutines',
        'getRoutineId',
        'logTask',
        'standardRoutineHandler',
    ];

    /**
     * The routines from the model, as a plain list.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  array<int, array<string, mixed>>  The routines.
     *
     * @since   0.1.0
     */
    private function routines(PluginModel $model): array
    {
        $routines = [];

        foreach ((array) $model->config('routines', []) as $routine) {
            if (\is_array($routine) && ($routine['id'] ?? '') !== '') {
                $routines[] = $routine;
            }
        }

        return $routines;
    }

    /**
     * The language constant prefix for one routine.
     *
     * Derived from the plugin prefix and the routine id, so the keys in the
     * language file and the ones in TASKS_MAP cannot drift apart.
     *
     * @param   PluginModel             $model    The plugin model.
     * @param   array<string, mixed>    $routine  The routine.
     *
     * @return  string  The language constant prefix.
     *
     * @since   0.1.0
     */
    private function langPrefix(PluginModel $model, array $routine): string
    {
        $id = (string) ($routine['id'] ?? '');

        // Routine ids are conventionally written as "plugin.routine", and the
        // language prefix already carries the plugin name. Without this the
        // recipes plugin would advertise PLG_TASK_RECIPES_RECIPES_CLEANUP.
        $segments = explode('.', $id);

        if (\count($segments) > 1 && strcasecmp($segments[0], $model->element) === 0) {
            array_shift($segments);
        }

        $tail = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', implode('_', $segments)));

        return $model->languagePrefix() . '_' . trim($tail, '_');
    }

    /**
     * The name of the parameter form for one routine.
     *
     * @param   array<string, mixed>  $routine  The routine.
     *
     * @return  string  The form name, without the .xml.
     *
     * @since   0.1.0
     */
    private function formName(array $routine): string
    {
        return (string) ($routine['method'] ?? 'params');
    }

    /**
     * A readable title for a routine that was given none.
     *
     * @param   array<string, mixed>  $routine  The routine.
     *
     * @return  string  The title.
     *
     * @since   0.1.0
     */
    private function titleFrom(array $routine): string
    {
        $id   = (string) ($routine['id'] ?? '');
        $tail = strrchr($id, '.');

        return ucwords(str_replace('_', ' ', $tail === false ? $id : substr($tail, 1)));
    }
}
