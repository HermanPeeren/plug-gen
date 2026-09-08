<?php

/**
 * @package     Pluggen
 * @subpackage  Types.Finder
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Types\Finder;

use Yepr\Component\Pluggen\Administrator\Generator\Emitter\PhpEmitter as Php;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\PluginTypeInterface;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;
use Yepr\Component\Pluggen\Administrator\Generator\Output\ProtectedRegionMerger;
use Yepr\Component\Pluggen\Administrator\Generator\Template\Renderer;

/**
 * Smart Search adapter plugins.
 *
 * A finder plugin is not a listener but an adapter: it extends com_finder's
 * abstract Adapter and is configured almost entirely by class properties. That
 * makes it unusually well suited to generation - the properties, the four event
 * handlers and the list query follow mechanically from the model.
 */
final class Definition implements PluginTypeInterface
{
    public function id(): string
    {
        return 'finder';
    }

    public function group(): string
    {
        return 'finder';
    }

    public function label(): string
    {
        return 'Finder (Smart Search adapter)';
    }

    public function path(): string
    {
        return __DIR__;
    }

    public function formPath(): ?string
    {
        return __DIR__ . \DIRECTORY_SEPARATOR . 'form.xml';
    }

    public function slots(): array
    {
        $meta = json_decode((string) file_get_contents(__DIR__ . '/type.json'), true);

        return \is_array($meta['slots'] ?? null) ? $meta['slots'] : [];
    }

    public function validate(PluginModel $model): array
    {
        $errors = [];

        $context = (string) $model->config('context', '');

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $context)) {
            $errors[] = 'The finder context must be a plain identifier, for example "Recipes".';
        } elseif (strtolower($context) !== $model->element) {
            // pluginDisable() matches getPluginType($id) against strtolower($context),
            // so a context that does not match the element silently never fires.
            $errors[] = \sprintf(
                'The finder context "%s" must match the plugin element "%s" apart from capitals, '
                . 'otherwise un-indexing on disable will not work.',
                $context,
                $model->element
            );
        }

        if (!preg_match('/^com_[a-z][a-z0-9_]*$/', (string) $model->config('extension', ''))) {
            $errors[] = 'The indexed extension must be a component name such as "com_recipes".';
        }

        // The event context is <extension>.<model name>, where the model name is
        // the component's, not the plugin's: com_recipes has a RecipeModel, so the
        // context is "com_recipes.recipe" while the plugin element is "recipes".
        if (!preg_match('/^[a-z][a-z0-9_]*$/', (string) $model->config('itemName', ''))) {
            $errors[] = 'The item name must be the component model name in lowercase, such as "recipe".';
        }

        if (!preg_match('/^#__[a-z][a-z0-9_]*$/', (string) $model->config('table', ''))) {
            $errors[] = 'The table must be written with the prefix placeholder, such as "#__recipes".';
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', (string) $model->config('stateField', 'state'))) {
            $errors[] = 'The state field must be a plain column name.';
        }

        foreach ((array) $model->config('columns', []) as $index => $column) {
            $name = \is_array($column) ? (string) ($column['column'] ?? '') : '';

            if (!preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $name)) {
                $errors[] = \sprintf('Column %d must be written as "a.column_name".', $index + 1);
            }

            $alias = \is_array($column) ? (string) ($column['alias'] ?? '') : '';

            if ($alias !== '' && !preg_match('/^[a-z][a-z0-9_]*$/', $alias)) {
                $errors[] = \sprintf('The alias for column %d is not a plain column name.', $index + 1);
            }
        }

        return $errors;
    }

    public function generate(PluginModel $model, FileCollection $files): void
    {
        $renderer = new Renderer();

        $path = 'src/Extension/' . Php::identifier($model->className) . '.php';

        // Templates get closures rather than class references: a template file has
        // no namespace of its own, and every value it interpolates must go through
        // an emitter anyway.
        $files->add($path, $renderer->render(__DIR__ . '/templates/Extension.php.tpl', [
            'm'      => $model,
            'str'    => static fn(mixed $value): string => Php::string($value),
            'arr'    => static fn(array $value, int $indent = 0): string => Php::arrayLiteral($value, $indent),
            'id'     => static fn(string $value): string => Php::identifier($value),
            'block'  => static fn(string $code, int $levels = 2): string => Php::indentBlock($code, $levels),
            'region' => static fn(string $slot, int $levels = 2): string
                => ProtectedRegionMerger::region($slot, Php::indentBlock($model->slot($slot), $levels), $levels),
        ]));
    }
}
