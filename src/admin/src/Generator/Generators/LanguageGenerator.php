<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Generators;

use Yepr\Component\Pluggen\Administrator\Generator\Emitter\IniEmitter as Ini;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;

/**
 * The en-GB language files, with a key for every parameter in the model.
 */
final class LanguageGenerator implements GeneratorInterface
{
    public function supports(PluginModel $model): bool
    {
        return true;
    }

    public function generate(PluginModel $model, FileCollection $files): void
    {
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

        $files->add('language/en-GB/' . $name . '.ini', implode("\n", $ini) . "\n");
        $files->add('language/en-GB/' . $name . '.sys.ini', implode("\n", $sys) . "\n");
    }

    private function title(PluginModel $model): string
    {
        return ucfirst($model->group) . ' - ' . ucfirst(str_replace('_', ' ', $model->element));
    }
}
