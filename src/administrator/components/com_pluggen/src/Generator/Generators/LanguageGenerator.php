<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Generators;

use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Emitter\IniEmitter as Ini;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\Output\FileCollection;

/**
 * The en-GB language files, with a key for every parameter in the model.
 *
 * @since  0.1.0
 */
final class LanguageGenerator implements GeneratorInterface
{
    /**
     * Constructor.
     *
     * @param   ?TypeRegistry  $types  The registry, so a plugin type can add the
     *                                 keys it needs; omit it for the generic keys only.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly ?TypeRegistry $types = null)
    {
    }

    /**
     * Every plugin needs language files.
     *
     * @param   ModelInterface  $model  The plugin model.
     *
     * @return  boolean  Always true.
     *
     * @since   0.1.0
     */
    public function supports(ModelInterface $model): bool
    {
        return $model instanceof PluginModel;
    }

    /**
     * Write the .ini and .sys.ini files.
     *
     * @param   PluginModel     $model  The plugin model.
     * @param   FileCollection  $files  The collection to add to.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function generate(ModelInterface $model, FileCollection $files): void
    {
        // Narrowed here rather than in the signature. The shared interface takes
        // a ModelInterface and an implementation may not ask for less, so the
        // type this generator actually reads is asserted in the body - which
        // `supports()` has already answered for the only caller there is.
        if (!$model instanceof PluginModel) {
            return;
        }

        $prefix      = $model->languagePrefix();
        $name        = $model->extensionName();
        $description = $model->description !== ''
            ? $model->description
            : \sprintf('The %s plugin.', $name);

        $sys   = [];
        $sys[] = Ini::line($prefix, $this->title($model));
        $sys[] = Ini::line($prefix . '_XML_DESCRIPTION', $description);

        $ini = $sys;

        if ($model->params !== []) {
            $ini[] = '';
            $ini[] = Ini::comment('Plugin parameters');

            foreach ($model->params as $param) {
                $paramName = strtoupper((string) ($param['name'] ?? ''));
                $label     = (string) ($param['label'] ?? ucwords(str_replace('_', ' ', strtolower($paramName))));

                $ini[] = Ini::line($prefix . '_FIELD_' . $paramName . '_LABEL', $label);

                foreach ((array) ($param['options'] ?? []) as $label) {
                    if (\is_string($label) && str_starts_with($label, $prefix)) {
                        $ini[] = Ini::line($label, ucwords(strtolower(str_replace('_', ' ', substr($label, \strlen($prefix) + 1)))));
                    }
                }
            }
        }

        $typeKeys = $this->typeKeys($model);

        if ($typeKeys !== []) {
            $ini[] = '';
            $ini[] = Ini::comment('Keys this plugin type needs');

            foreach ($typeKeys as $key => $text) {
                $ini[] = Ini::line($key, $text);
            }
        }

        $files->add('language/en-GB/' . $name . '.ini', implode("\n", $ini) . "\n");
        $files->add('language/en-GB/' . $name . '.sys.ini', implode("\n", $sys) . "\n");
    }

    /**
     * The language keys the plugin type asks for.
     *
     * @param   PluginModel     $model  The plugin model.
     *
     * @return  array<string, string>  Language key => text.
     *
     * @since   0.1.0
     */
    private function typeKeys(PluginModel $model): array
    {
        if ($this->types === null || $model->typeId === '' || !$this->types->has($model->typeId)) {
            return [];
        }

        return $this->types->get($model->typeId)->languageKeys($model);
    }

    /**
     * The human readable plugin title, in Joomla's "Group - Name" convention.
     *
     * @param   PluginModel     $model  The plugin model.
     *
     * @return  string  The title.
     *
     * @since   0.1.0
     */
    private function title(PluginModel $model): string
    {
        return ucfirst($model->group) . ' - ' . $model->name;
    }
}
