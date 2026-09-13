<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Model;

/**
 * An immutable description of one plugin: the model from which code is generated.
 *
 * This is the only input to generation. Nothing in the generators may read from
 * the request, the database, the filesystem or global state.
 *
 * @since  0.1.0
 */
final class PluginModel
{
    /**
     * The model format this generator reads and writes.
     *
     * @var    string
     * @since  0.1.0
     */
    public const CURRENT_VERSION = '1.0';

    /**
     * Constructor.
     *
     * Private on purpose: a model is built from stored data through fromArray()
     * or fromJson(), never assembled field by field.
     *
     * @param   string   $modelVersion      The model format version.
     * @param   string   $target            The Joomla version the output targets.
     * @param   string   $group             The plugin group.
     * @param   string   $element           The plugin element name.
     * @param   string   $namespace         The PHP namespace, without leading backslash.
     * @param   string   $className         The plugin class name.
     * @param   string   $version           The plugin version.
     * @param   string   $description       The plugin description.
     * @param   string   $authorName        The author name.
     * @param   string   $authorEmail       The author email address.
     * @param   string   $authorUrl         The author URL.
     * @param   string   $copyright         The copyright line.
     * @param   boolean  $autoloadLanguage  Whether to load the language file on instantiation.
     * @param   array    $services          The services to inject, as name => bool.
     * @param   array    $params            The plugin's own parameter definitions.
     * @param   string   $typeId            The plugin type id.
     * @param   array    $typeConfig        The type-specific configuration.
     * @param   array    $slots             The custom code per slot id.
     *
     * @since   0.1.0
     */
    private function __construct(
        public readonly string $modelVersion,
        public readonly string $target,
        public readonly string $group,
        public readonly string $element,
        public readonly string $namespace,
        public readonly string $className,
        public readonly string $version,
        public readonly string $description,
        public readonly string $authorName,
        public readonly string $authorEmail,
        public readonly string $authorUrl,
        public readonly string $copyright,
        public readonly bool $autoloadLanguage,
        public readonly array $services,
        public readonly array $params,
        public readonly string $typeId,
        public readonly array $typeConfig,
        public readonly array $slots
    ) {
    }

    /**
     * Build a model from a decoded JSON structure.
     *
     * Missing values get defaults; validity is the job of ModelValidator, not of
     * this constructor. Keeping the two apart means an incomplete model can be
     * loaded and shown back to the user instead of blowing up on the way in.
     *
     * @param   array  $data  The decoded model.
     *
     * @return  self  The model.
     *
     * @since   0.1.0
     */
    public static function fromArray(array $data): self
    {
        $plugin = self::asArray($data['plugin'] ?? []);
        $type   = self::asArray($data['type'] ?? []);
        $author = self::asArray($plugin['author'] ?? []);

        $element = self::asString($plugin['element'] ?? '');

        return new self(
            modelVersion: self::asString($data['modelVersion'] ?? self::CURRENT_VERSION),
            target: self::asString($data['target'] ?? 'joomla-6.0'),
            group: self::asString($plugin['group'] ?? ''),
            element: $element,
            namespace: trim(self::asString($plugin['namespace'] ?? ''), '\\'),
            className: self::asString($plugin['className'] ?? '') ?: ucfirst($element),
            version: self::asString($plugin['version'] ?? '1.0.0'),
            description: self::asString($plugin['description'] ?? ''),
            authorName: self::asString($author['name'] ?? ''),
            authorEmail: self::asString($author['email'] ?? ''),
            authorUrl: self::asString($author['url'] ?? ''),
            copyright: self::asString($plugin['copyright'] ?? ''),
            autoloadLanguage: (bool) ($plugin['autoloadLanguage'] ?? true),
            services: self::asArray($plugin['services'] ?? []),
            params: array_values(self::asArray($plugin['params'] ?? [])),
            typeId: self::asString($type['id'] ?? ''),
            typeConfig: self::asArray($type['config'] ?? []),
            slots: self::asArray($data['slots'] ?? [])
        );
    }

    /**
     * Build a model from its stored JSON representation.
     *
     * @param   string  $json  The stored model.
     *
     * @return  self  The model.
     *
     * @throws  ValidationException  When the JSON cannot be decoded.
     *
     * @since   0.1.0
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            throw new ValidationException(['Model is not valid JSON: ' . json_last_error_msg()]);
        }

        return self::fromArray($data);
    }

    /**
     * Round-trip back to the stored format.
     *
     * Keys are emitted in a fixed order so that saving a model twice produces
     * identical JSON, which keeps diffs of stored models readable.
     *
     * @return  array  The model in its stored shape.
     *
     * @since   0.1.0
     */
    public function toArray(): array
    {
        return [
            'modelVersion' => $this->modelVersion,
            'target'       => $this->target,
            'plugin'       => [
                'group'            => $this->group,
                'element'          => $this->element,
                'namespace'        => $this->namespace,
                'className'        => $this->className,
                'version'          => $this->version,
                'description'      => $this->description,
                'copyright'        => $this->copyright,
                'author'           => [
                    'name'  => $this->authorName,
                    'email' => $this->authorEmail,
                    'url'   => $this->authorUrl,
                ],
                'autoloadLanguage' => $this->autoloadLanguage,
                'services'         => $this->services,
                'params'           => $this->params,
            ],
            'type'         => [
                'id'     => $this->typeId,
                'config' => $this->typeConfig,
            ],
            'slots'        => $this->slots,
        ];
    }

    /**
     * The model as pretty printed JSON, ready to be stored.
     *
     * @return  string  The JSON representation.
     *
     * @since   0.1.0
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The installed extension name, for example "plg_finder_recipes".
     *
     * @return  string  The extension name.
     *
     * @since   0.1.0
     */
    public function extensionName(): string
    {
        return 'plg_' . $this->group . '_' . $this->element;
    }

    /**
     * The language key prefix, for example "PLG_FINDER_RECIPES".
     *
     * @return  string  The prefix.
     *
     * @since   0.1.0
     */
    public function languagePrefix(): string
    {
        return strtoupper($this->extensionName());
    }

    /**
     * The namespace the plugin class lives in, for example
     * "Acme\Plugin\Finder\Recipes\Extension".
     *
     * @return  string  The namespace of the plugin class.
     *
     * @since   0.1.0
     */
    public function extensionNamespace(): string
    {
        return $this->namespace . '\\Extension';
    }

    /**
     * The custom code stored for one slot.
     *
     * @param   string  $id  The slot id, for example "finder.index.elements".
     *
     * @return  string  The code, or an empty string when the slot was left blank.
     *
     * @since   0.1.0
     */
    public function slot(string $id): string
    {
        $slot = $this->slots[$id] ?? null;

        if (\is_array($slot)) {
            return trim((string) ($slot['code'] ?? ''));
        }

        return \is_string($slot) ? trim($slot) : '';
    }

    /**
     * Whether a service should be injected into the generated plugin.
     *
     * @param   string  $name  The service key, for example "database".
     *
     * @return  boolean  True when the service was selected.
     *
     * @since   0.1.0
     */
    public function service(string $name): bool
    {
        return (bool) ($this->services[$name] ?? false);
    }

    /**
     * Read one value from the type-specific configuration.
     *
     * @param   string  $key      The configuration key.
     * @param   mixed   $default  Returned when the key is not set.
     *
     * @return  mixed  The configured value, or the default.
     *
     * @since   0.1.0
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->typeConfig[$key] ?? $default;
    }

    /**
     * Coerce a stored value to a trimmed string, ignoring anything non-scalar.
     *
     * @param   mixed  $value  The raw value from the stored model.
     *
     * @return  string  The string value, or an empty string.
     *
     * @since   0.1.0
     */
    private static function asString(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Coerce a stored value to an array.
     *
     * @param   mixed  $value  The raw value from the stored model.
     *
     * @return  array  The array value, or an empty array.
     *
     * @since   0.1.0
     */
    private static function asArray(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }
}
