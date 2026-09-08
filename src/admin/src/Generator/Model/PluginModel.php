<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Model;

/**
 * An immutable description of one plugin: the model from which code is generated.
 *
 * This is the only input to generation. Nothing in the generators may read from
 * the request, the database, the filesystem or global state.
 */
final class PluginModel
{
    public const CURRENT_VERSION = '1.0';

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
     * Build a model from a decoded JSON structure. Missing values get defaults;
     * validity is the job of ModelValidator, not of this constructor.
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

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            throw new ValidationException(['Model is not valid JSON: ' . json_last_error_msg()]);
        }

        return self::fromArray($data);
    }

    /**
     * Round-trips back to the stored format. Keys are emitted in a fixed order so
     * that saving a model twice produces identical JSON.
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

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** plg_finder_recipes */
    public function extensionName(): string
    {
        return 'plg_' . $this->group . '_' . $this->element;
    }

    /** PLG_FINDER_RECIPES */
    public function languagePrefix(): string
    {
        return strtoupper($this->extensionName());
    }

    /** Yepr\Plugin\Finder\Recipes\Extension */
    public function extensionNamespace(): string
    {
        return $this->namespace . '\\Extension';
    }

    /** A slot's code, or an empty string when the slot was left blank. */
    public function slot(string $id): string
    {
        $slot = $this->slots[$id] ?? null;

        if (\is_array($slot)) {
            return trim((string) ($slot['code'] ?? ''));
        }

        return \is_string($slot) ? trim($slot) : '';
    }

    public function service(string $name): bool
    {
        return (bool) ($this->services[$name] ?? false);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->typeConfig[$key] ?? $default;
    }

    private static function asString(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }

    private static function asArray(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }
}
