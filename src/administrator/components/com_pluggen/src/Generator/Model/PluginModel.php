<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Model;

use Yepr\Gen\Core\Model\ModelInterface;

/**
 * An immutable description of one plugin: the model from which code is generated.
 *
 * This is the only input to generation. Nothing in the generators may read from
 * the request, the database, the filesystem or global state.
 *
 * `ModelInterface` is empty on purpose and this implements it anyway: the
 * shared engine never looks inside a model, so what the marker buys is a named
 * boundary - a generator declares that it takes a model rather than any object,
 * and this component can still be checked statically against its own type.
 *
 * @since  0.1.0
 */
final class PluginModel implements ModelInterface
{
    /**
     * The model format this generator reads and writes.
     *
     * @var    string
     * @since  0.1.0
     */
    public const CURRENT_VERSION = '1.1';

    /**
     * Constructor.
     *
     * Private on purpose: a model is built from stored data through fromArray()
     * or fromJson(), never assembled field by field.
     *
     * @param   string   $modelVersion      The model format version.
     * @param   string   $target            The Joomla version the output targets.
     * @param   string   $group             The plugin group.
     * @param   string   $name              The plugin's name, as a person would write it.
     * @param   string   $orgNamespace      The organisation namespace, for example "Acme".
     * @param   string   $version           The plugin version.
     * @param   string   $description       The plugin description.
     * @param   string   $authorName        The author name.
     * @param   string   $authorEmail       The author email address.
     * @param   string   $authorUrl         The author URL.
     * @param   string   $copyright         The copyright line.
     * @param   boolean  $autoloadLanguage  Whether to load the language file on instantiation.
     * @param   array    $services          The stock services to inject, as name => bool.
     * @param   array    $customServices    Extra services to inject, each with name, expression and use.
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
        public readonly string $name,
        public readonly string $orgNamespace,
        public readonly string $version,
        public readonly string $description,
        public readonly string $authorName,
        public readonly string $authorEmail,
        public readonly string $authorUrl,
        public readonly string $copyright,
        public readonly bool $autoloadLanguage,
        public readonly array $services,
        public readonly array $customServices,
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

        $version = self::asString($data['modelVersion'] ?? self::CURRENT_VERSION);

        // A model written before the 1.1 format is read, not rejected: the same
        // facts were simply spelled differently. Anything else keeps the version
        // it came with, so ModelValidator can refuse a format this code does not
        // know instead of silently misreading it.
        if ($version === '1.0') {
            $plugin  = self::upgradeFrom10($plugin);
            $version = self::CURRENT_VERSION;
        }

        return new self(
            modelVersion: $version,
            target: self::asString($data['target'] ?? 'joomla-6.0'),
            group: self::asString($plugin['group'] ?? ''),
            name: self::asString($plugin['name'] ?? ''),
            orgNamespace: trim(self::asString($plugin['orgNamespace'] ?? ''), '\\'),
            version: self::asString($plugin['version'] ?? '1.0.0'),
            description: self::asString($plugin['description'] ?? ''),
            authorName: self::asString($author['name'] ?? ''),
            authorEmail: self::asString($author['email'] ?? ''),
            authorUrl: self::asString($author['url'] ?? ''),
            copyright: self::asString($plugin['copyright'] ?? ''),
            autoloadLanguage: (bool) ($plugin['autoloadLanguage'] ?? true),
            services: self::asArray($plugin['services'] ?? []),
            customServices: array_values(self::asArray($plugin['customServices'] ?? [])),
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
                'name'             => $this->name,
                'orgNamespace'     => $this->orgNamespace,
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
                'customServices'   => $this->customServices,
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
        return 'plg_' . $this->group . '_' . $this->elementName();
    }

    /**
     * The package file name, for example "plg_finder_recipes-1.0.0".
     *
     * The version belongs in the name for the same reason it does in Plug-gen's
     * own releases: a downloads folder full of plg_finder_recipes.zip files
     * says nothing about which is which, and the one that matters is usually
     * not the newest by timestamp.
     *
     * @return  string  The package name, without an extension.
     *
     * @since   0.4.3
     */
    public function packageName(): string
    {
        return $this->extensionName() . '-' . $this->version;
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
     * The plugin class name, for example "ArticleUpdateNotification".
     *
     * The name in PascalCase. Only letters and digits survive, each word
     * capitalised: "Article update notification", "article-update-notification"
     * and "Article Update Notification" all arrive here as the same class.
     * Capitals already inside a word are kept, so "HTML cleaner" gives
     * "HTMLCleaner" rather than "HtmlCleaner".
     *
     * @return  string  The class name.
     *
     * @since   0.4.2
     */
    public function className(): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $this->name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(static fn(string $word): string => ucfirst($word), $words));
    }

    /**
     * The plugin element, for example "articleupdatenotification".
     *
     * What Joomla calls the element: the folder under plugins/<group>/, the
     * element column in #__extensions, and the tail of the language key. Joomla
     * writes these in lowercase and without separators, which is the class name
     * with its capitals dropped.
     *
     * @return  string  The element name.
     *
     * @since   0.4.2
     */
    public function elementName(): string
    {
        return strtolower($this->className());
    }

    /**
     * The plugin's own namespace, for example "Acme\Plugin\Finder\Recipes".
     *
     * Derived, never asked for: Joomla fixes every part of it but the
     * organisation. A plugin whose namespace disagrees with its group or its
     * name does not autoload, and that is a wrong answer no form should
     * be able to give.
     *
     * @return  string  The namespace, without leading backslash.
     *
     * @since   0.4.2
     */
    public function rootNamespace(): string
    {
        return implode('\\', [$this->orgNamespace, 'Plugin', $this->groupStudly(), $this->className()]);
    }

    /**
     * The namespace the plugin class lives in.
     *
     * @return  string  The namespace of the plugin class.
     *
     * @since   0.1.0
     */
    public function extensionNamespace(): string
    {
        return $this->rootNamespace() . '\\Extension';
    }

    /**
     * The plugin group as it appears in a namespace.
     *
     * Joomla studly-cases the group and drops the hyphens, so the group
     * "api-authentication" becomes "ApiAuthentication" - as in core's own
     * Joomla\Plugin\ApiAuthentication\Basic.
     *
     * @return  string  The group in namespace form.
     *
     * @since   0.4.2
     */
    private function groupStudly(): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $this->group)));
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
     * The major Joomla version the output targets.
     *
     * Targets are stored as "joomla-6.0", so the number after the dash is the
     * major line. Anything unreadable counts as the oldest supported line: a
     * generator asking "may I use this?" should get "no" from a value it does
     * not understand, never a plugin that fatals on the user's site.
     *
     * @return  integer  The major version, for example 6.
     *
     * @since   0.4.2
     */
    public function targetMajor(): int
    {
        return preg_match('/^joomla-(\d+)\./', $this->target, $m) === 1 ? (int) $m[1] : 5;
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
     * Bring a 1.0 plugin block up to the current format.
     *
     * The form stopped asking for what it could work out. Where 1.0 stored an
     * element, a class name and a namespace, there is now one human readable
     * name that all three are derived from - so the class name is what to keep,
     * split back into words, since it is the only one of the three that still
     * carries them. "namespace" went away because it follows from the name and
     * the group. The
     * organisation is what stood before "\Plugin\" in the old namespace, or its
     * first segment when that namespace did not follow the convention.
     *
     * @param   array  $plugin  The plugin block of a 1.0 model.
     *
     * @return  array  The plugin block in the current format.
     *
     * @since   0.4.2
     */
    private static function upgradeFrom10(array $plugin): array
    {
        $source = self::asString(
            $plugin['className'] ?? ucfirst((string) ($plugin['element'] ?? ''))
        );

        // PascalCase back into words: the capitals were the word boundaries. A
        // run of capitals stays one word, so "HTMLCleaner" is "HTML Cleaner"
        // and not four words.
        $plugin['name'] = trim(
            (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $source)
        );

        if (!isset($plugin['orgNamespace'])) {
            $namespace = trim(self::asString($plugin['namespace'] ?? ''), '\\');
            $parts     = explode('\\Plugin\\', $namespace);

            $plugin['orgNamespace'] = $parts[0] === $namespace
                ? explode('\\', $namespace)[0]
                : $parts[0];
        }

        unset($plugin['element'], $plugin['namespace'], $plugin['className']);

        return $plugin;
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
