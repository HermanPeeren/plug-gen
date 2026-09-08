<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Metamodel;

/**
 * Discovers the plugin type bundles.
 *
 * Scans a directory for subfolders containing a Definition.php, and instantiates
 * the class by convention: <baseNamespace>\<Folder>\Definition. Types can also be
 * registered explicitly, which is the seam a third party extension would use.
 */
final class TypeRegistry
{
    /** @var array<string, PluginTypeInterface> */
    private array $types = [];

    private bool $scanned = false;

    public function __construct(
        private readonly string $typesPath,
        private readonly string $baseNamespace = __NAMESPACE__
    ) {
    }

    /** The default registry: the Types folder that ships with the component. */
    public static function default(): self
    {
        return new self(
            \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Types',
            'Yepr\\Component\\Pluggen\\Administrator\\Types'
        );
    }

    public function register(PluginTypeInterface $type): void
    {
        $this->types[$type->id()] = $type;
    }

    public function has(string $id): bool
    {
        $this->scan();

        return isset($this->types[$id]);
    }

    public function get(string $id): PluginTypeInterface
    {
        $this->scan();

        if (!isset($this->types[$id])) {
            throw new \OutOfBoundsException(\sprintf('Unknown plugin type "%s".', $id));
        }

        return $this->types[$id];
    }

    /** @return array<string, PluginTypeInterface> Sorted by id. */
    public function all(): array
    {
        $this->scan();

        $types = $this->types;
        ksort($types, SORT_STRING);

        return $types;
    }

    /** @return array<string, string> id => label, for a form list field. */
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $id => $type) {
            $options[$id] = $type->label();
        }

        return $options;
    }

    private function scan(): void
    {
        if ($this->scanned) {
            return;
        }

        $this->scanned = true;

        if (!is_dir($this->typesPath)) {
            return;
        }

        foreach (scandir($this->typesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            // Only plain names: nothing here should ever look like a path.
            if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $entry)) {
                continue;
            }

            $definition = $this->typesPath . \DIRECTORY_SEPARATOR . $entry . \DIRECTORY_SEPARATOR . 'Definition.php';

            if (!is_file($definition)) {
                continue;
            }

            $class = $this->baseNamespace . '\\' . $entry . '\\Definition';

            if (!class_exists($class)) {
                continue;
            }

            $instance = new $class();

            if ($instance instanceof PluginTypeInterface && !isset($this->types[$instance->id()])) {
                $this->types[$instance->id()] = $instance;
            }
        }
    }
}
