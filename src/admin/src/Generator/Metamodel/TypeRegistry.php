<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Metamodel;

use Yepr\Component\Pluggen\Administrator\Generator\Template\Renderer;
use Yepr\Component\Pluggen\Administrator\Generator\Template\RendererAwareInterface;

/**
 * Discovers the plugin type bundles.
 *
 * Scans a directory for subfolders containing a Definition.php, and instantiates
 * the class by convention: <baseNamespace>\<Folder>\Definition. Types can also be
 * registered explicitly, which is the seam a third party extension would use.
 *
 * @since  0.1.0
 */
final class TypeRegistry
{
    /**
     * The registered types, keyed by id.
     *
     * @var    array<string, PluginTypeInterface>
     * @since  0.1.0
     */
    private array $types = [];

    /**
     * Whether the types directory has been scanned yet.
     *
     * @var    boolean
     * @since  0.1.0
     */
    private bool $scanned = false;

    /**
     * Constructor.
     *
     * @param   string     $typesPath      Absolute path of the folder holding the type bundles.
     * @param   string     $baseNamespace  The namespace the bundle classes live in.
     * @param   ?Renderer  $renderer       The renderer handed to bundles that render templates.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly string $typesPath,
        private readonly string $baseNamespace = __NAMESPACE__,
        private readonly ?Renderer $renderer = null
    ) {
    }

    /**
     * The default registry: the Types folder that ships with the component.
     *
     * A composition helper for callers that have no container - the fixture tool
     * and the tests. Inside the component the registry is built by the service
     * provider instead.
     *
     * @return  self  A registry over the bundled types.
     *
     * @since   0.1.0
     */
    public static function default(): self
    {
        return new self(
            \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'Types',
            'Yepr\\Component\\Pluggen\\Administrator\\Types',
            new Renderer()
        );
    }

    /**
     * Register a type explicitly, for instance one supplied by another extension.
     *
     * @param   PluginTypeInterface  $type  The type definition.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function register(PluginTypeInterface $type): void
    {
        $this->types[$type->id()] = $type;
    }

    /**
     * Whether a type with this id is available.
     *
     * @param   string  $id  The type id.
     *
     * @return  boolean  True when the type is registered.
     *
     * @since   0.1.0
     */
    public function has(string $id): bool
    {
        $this->scan();

        return isset($this->types[$id]);
    }

    /**
     * Get one type definition.
     *
     * @param   string  $id  The type id.
     *
     * @return  PluginTypeInterface  The type definition.
     *
     * @throws  \OutOfBoundsException  When no such type is registered.
     *
     * @since   0.1.0
     */
    public function get(string $id): PluginTypeInterface
    {
        $this->scan();

        if (!isset($this->types[$id])) {
            throw new \OutOfBoundsException(\sprintf('Unknown plugin type "%s".', $id));
        }

        return $this->types[$id];
    }

    /**
     * All registered types, sorted by id so that output stays deterministic.
     *
     * @return  array<string, PluginTypeInterface>  The types, keyed by id.
     *
     * @since   0.1.0
     */
    public function all(): array
    {
        $this->scan();

        $types = $this->types;
        ksort($types, SORT_STRING);

        return $types;
    }

    /**
     * The types as options for a form list field.
     *
     * @return  array<string, string>  Type id => label.
     *
     * @since   0.1.0
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $id => $type) {
            $options[$id] = $type->label();
        }

        return $options;
    }

    /**
     * The registered types indexed by the plugin group they generate into.
     *
     * Today every group has at most one type, which is why the interface can
     * offer a single choice. The index is by group rather than by id so that the
     * day a group gains a second type - "content: event listener" next to
     * "content: Smart Search bridge" - only this method has to change.
     *
     * @return  array<string, PluginTypeInterface>  Group name => type.
     *
     * @since   0.1.0
     */
    public function byGroup(): array
    {
        $byGroup = [];

        foreach ($this->all() as $type) {
            $byGroup[$type->group()] ??= $type;
        }

        return $byGroup;
    }

    /**
     * Whether a generator is available for this plugin group.
     *
     * @param   string  $group  The plugin group.
     *
     * @return  boolean  True when a type bundle can generate for that group.
     *
     * @since   0.1.0
     */
    public function hasGroup(string $group): bool
    {
        return isset($this->byGroup()[$group]);
    }

    /**
     * The type that generates plugins for a group.
     *
     * @param   string  $group  The plugin group.
     *
     * @return  PluginTypeInterface  The type definition.
     *
     * @throws  \OutOfBoundsException  When no bundle covers that group.
     *
     * @since   0.1.0
     */
    public function forGroup(string $group): PluginTypeInterface
    {
        $byGroup = $this->byGroup();

        if (!isset($byGroup[$group])) {
            throw new \OutOfBoundsException(\sprintf('No generator is available for the "%s" plugin group.', $group));
        }

        return $byGroup[$group];
    }

    /**
     * Every known plugin group with the type that covers it, if any.
     *
     * Groups without a bundle are included with a null type, so the interface can
     * show them greyed out rather than hiding that they exist.
     *
     * @return  array<string, ?PluginTypeInterface>  Group name => type or null.
     *
     * @since   0.1.0
     */
    public function availability(): array
    {
        $byGroup      = $this->byGroup();
        $availability = [];

        foreach (PluginGroups::names() as $group) {
            $availability[$group] = $byGroup[$group] ?? null;
        }

        // A bundle for a group outside the core list still belongs in the list.
        foreach ($byGroup as $group => $type) {
            $availability[$group] ??= $type;
        }

        return $availability;
    }

    /**
     * Scan the types folder once, instantiating every bundle it finds.
     *
     * Folder names are matched against a strict pattern: nothing discovered here
     * should ever look like a path.
     *
     * @return  void
     *
     * @since   0.1.0
     */
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

            if ($instance instanceof RendererAwareInterface && $this->renderer !== null) {
                $instance->setRenderer($this->renderer);
            }

            if ($instance instanceof PluginTypeInterface && !isset($this->types[$instance->id()])) {
                $this->types[$instance->id()] = $instance;
            }
        }
    }
}
