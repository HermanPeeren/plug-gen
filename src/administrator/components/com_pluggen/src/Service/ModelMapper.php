<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Service;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Translates between the edit form and the stored model.
 *
 * The form is a flat Joomla structure; the model is the versioned JSON the
 * generators consume. Keeping the translation in one place means the form can be
 * reorganised without touching the model format, which is the whole point of
 * having a model in the first place.
 *
 * Convention: a type bundle declares its fields in the groups "config_<id>" and
 * "slots_<id>", so two types can both have a field called "context" without
 * colliding in the shared form.
 *
 * @since  0.1.0
 */
final class ModelMapper
{
    /**
     * Constructor.
     *
     * @param   TypeRegistry  $types  The registry of available plugin types.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly TypeRegistry $types)
    {
    }

    /**
     * Turn form data into the model array that is stored as JSON.
     *
     * @param   array  $data  The submitted jform data.
     *
     * @return  array  The model, ready to be encoded.
     *
     * @since   0.1.0
     */
    public function toModel(array $data): array
    {
        $typeId = (string) ($data['plugin_type'] ?? '');

        return [
            'modelVersion' => PluginModel::CURRENT_VERSION,
            'target'       => (string) ($data['target'] ?? 'joomla-6.0'),
            'plugin'       => [
                'group'            => $this->group($typeId),
                // Lowercased here rather than in the model: this is the edge the
                // user's typing arrives at, and normalising it further in would
                // mean the model quietly accepted what the validator forbids.
                'systemName'       => strtolower(trim((string) ($data['system_name'] ?? ''))),
                'orgNamespace'     => trim((string) ($data['org_namespace'] ?? ''), '\\ '),
                'version'          => (string) ($data['version'] ?? '1.0.0'),
                'description'      => (string) ($data['description'] ?? ''),
                'copyright'        => (string) ($data['copyright'] ?? ''),
                'author'           => [
                    'name'  => (string) ($data['author_name'] ?? ''),
                    'email' => (string) ($data['author_email'] ?? ''),
                    'url'   => (string) ($data['author_url'] ?? ''),
                ],
                'autoloadLanguage' => (bool) ($data['autoloadLanguage'] ?? true),
                'services'         => $this->services((array) ($data['services'] ?? [])),
                'customServices'   => $this->customServices((array) ($data['custom_services'] ?? [])),
                'params'           => array_values((array) ($data['params'] ?? [])),
            ],
            'type'         => [
                'id'     => $typeId,
                'config' => $this->config($typeId, $data),
            ],
            'slots'        => $this->slots($typeId, $data),
        ];
    }

    /**
     * Unfold a stored model into the flat structure the form binds to.
     *
     * @param   array  $model  The decoded model.
     *
     * @return  array  Data for the edit form.
     *
     * @since   0.1.0
     */
    public function toForm(array $model): array
    {
        $plugin = (array) ($model['plugin'] ?? []);
        $author = (array) ($plugin['author'] ?? []);
        $type   = (array) ($model['type'] ?? []);
        $typeId = (string) ($type['id'] ?? '');

        $data = [
            'target'           => (string) ($model['target'] ?? 'joomla-6.0'),
            'system_name'      => (string) ($plugin['systemName'] ?? ''),
            'org_namespace'    => (string) ($plugin['orgNamespace'] ?? ''),
            'version'          => (string) ($plugin['version'] ?? '1.0.0'),
            'description'      => (string) ($plugin['description'] ?? ''),
            'copyright'        => (string) ($plugin['copyright'] ?? ''),
            'author_name'      => (string) ($author['name'] ?? ''),
            'author_email'     => (string) ($author['email'] ?? ''),
            'author_url'       => (string) ($author['url'] ?? ''),
            'autoloadLanguage' => (int) ($plugin['autoloadLanguage'] ?? 1),
            'services'         => array_keys(array_filter((array) ($plugin['services'] ?? []))),
            'custom_services'  => array_values((array) ($plugin['customServices'] ?? [])),
            'params'           => (array) ($plugin['params'] ?? []),
            'plugin_type'      => $typeId !== '' ? $typeId : (string) ($plugin['group'] ?? ''),
        ];

        if ($typeId !== '') {
            $data['config_' . $typeId] = (array) ($type['config'] ?? []);
            $data['slots_' . $typeId]  = $this->slotsToForm($typeId, (array) ($model['slots'] ?? []));
        }

        return $data;
    }

    /**
     * The plugin group a type generates into.
     *
     * Derived rather than asked for: the form offers one choice, and the stored
     * model still records the group explicitly so a generator reading the JSON
     * never has to resolve a type to learn where the plugin belongs.
     *
     * The selected value falls back to itself when no bundle is registered,
     * because for every type shipped so far the id and the group are the same
     * name - and a blueprint saved for a group whose bundle was removed should
     * still say which group it was for.
     *
     * @param   string  $typeId  The selected plugin type.
     *
     * @return  string  The plugin group.
     *
     * @since   0.1.0
     */
    private function group(string $typeId): string
    {
        if ($typeId !== '' && $this->types->has($typeId)) {
            return $this->types->get($typeId)->group();
        }

        return $typeId;
    }

    /**
     * Turn the selected service names into the model's name => bool map.
     *
     * @param   array  $selected  The checkbox values from the form.
     *
     * @return  array<string, boolean>  The services to inject.
     *
     * @since   0.1.0
     */
    private function services(array $selected): array
    {
        $services = [];

        foreach ($selected as $service) {
            if (\is_string($service) && preg_match('/^[a-zA-Z]+$/', $service)) {
                $services[$service] = true;
            }
        }

        return $services;
    }

    /**
     * Keep the freely declared services that say something.
     *
     * A repeatable subform posts an empty row whenever the user opened one and
     * thought better of it, so a row without both a name and an expression is
     * dropped rather than stored and later reported as invalid. Whether what is
     * kept is usable is ModelValidator's question.
     *
     * @param   array  $rows  The subform rows from the form.
     *
     * @return  array  The services to inject, each with name, use and expression.
     *
     * @since   0.4.2
     */
    private function customServices(array $rows): array
    {
        $services = [];

        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $name       = trim((string) ($row['name'] ?? ''));
            $expression = trim((string) ($row['expression'] ?? ''));

            if ($name === '' || $expression === '') {
                continue;
            }

            $services[] = [
                'name'       => $name,
                'use'        => trim((string) ($row['use'] ?? '')),
                'expression' => $expression,
            ];
        }

        return $services;
    }

    /**
     * Collect the type-specific configuration from its own form group.
     *
     * @param   string  $typeId  The selected plugin type.
     * @param   array   $data    The submitted jform data.
     *
     * @return  array  The type configuration.
     *
     * @since   0.1.0
     */
    private function config(string $typeId, array $data): array
    {
        if ($typeId === '' || !$this->types->has($typeId)) {
            return [];
        }

        $config = (array) ($data['config_' . $typeId] ?? []);

        // Multi-value fields arrive as comma separated strings from some layouts.
        foreach ($config as $key => $value) {
            if (\is_string($value) && $key === 'taxonomies') {
                $config[$key] = array_values(array_filter(array_map('trim', explode(',', $value))));
            }
        }

        return $config;
    }

    /**
     * Collect the custom code per slot.
     *
     * Only slots the type actually declares are stored: a field that is not a
     * known slot is dropped rather than written into the model.
     *
     * @param   string  $typeId  The selected plugin type.
     * @param   array   $data    The submitted jform data.
     *
     * @return  array  The slots, keyed by slot id.
     *
     * @since   0.1.0
     */
    private function slots(string $typeId, array $data): array
    {
        if ($typeId === '' || !$this->types->has($typeId)) {
            return [];
        }

        $submitted = (array) ($data['slots_' . $typeId] ?? []);
        $slots     = [];

        // Only slots the type actually declares are stored: a field name that is
        // not a known slot is dropped rather than written into the model.
        foreach (array_keys($this->types->get($typeId)->slots()) as $slotId) {
            $code = trim((string) ($submitted[str_replace('.', '_', $slotId)] ?? ''));

            if ($code !== '') {
                $slots[$slotId] = ['language' => 'php', 'code' => $code];
            }
        }

        return $slots;
    }

    /**
     * Unfold stored slots into form field names.
     *
     * Field names cannot contain dots, so a slot id such as
     * "finder.index.elements" becomes the field "finder_index_elements".
     *
     * @param   string  $typeId  The selected plugin type.
     * @param   array   $slots   The stored slots.
     *
     * @return  array  Field name => code.
     *
     * @since   0.1.0
     */
    private function slotsToForm(string $typeId, array $slots): array
    {
        $fields = [];

        foreach (array_keys($this->types->get($typeId)->slots()) as $slotId) {
            $slot = $slots[$slotId] ?? null;
            $code = \is_array($slot) ? (string) ($slot['code'] ?? '') : (string) $slot;

            $fields[str_replace('.', '_', $slotId)] = $code;
        }

        return $fields;
    }
}
