<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Generators;

use Yepr\Component\Pluggen\Administrator\Generator\Emitter\XmlEmitter as Xml;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;

/**
 * The plugin manifest: <element>.xml at the root of the package.
 *
 * @since  0.1.0
 */
final class ManifestGenerator implements GeneratorInterface
{
    /**
     * Every plugin needs a manifest.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  boolean  Always true.
     *
     * @since   0.1.0
     */
    public function supports(PluginModel $model): bool
    {
        return true;
    }

    /**
     * Write the manifest.
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
        $prefix = $model->languagePrefix();

        $lines   = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<extension type="plugin" group="' . Xml::attr($model->group) . '" method="upgrade">';
        $lines[] = "\t" . '<name>' . Xml::text($model->extensionName()) . '</name>';

        if ($model->authorName !== '') {
            $lines[] = "\t" . '<author>' . Xml::text($model->authorName) . '</author>';
        }

        if ($model->authorEmail !== '') {
            $lines[] = "\t" . '<authorEmail>' . Xml::text($model->authorEmail) . '</authorEmail>';
        }

        if ($model->authorUrl !== '') {
            $lines[] = "\t" . '<authorUrl>' . Xml::text($model->authorUrl) . '</authorUrl>';
        }

        if ($model->copyright !== '') {
            $lines[] = "\t" . '<copyright>' . Xml::text($model->copyright) . '</copyright>';
        }

        $lines[] = "\t" . '<license>GNU General Public License version 2 or later; see LICENSE.txt</license>';
        $lines[] = "\t" . '<version>' . Xml::text($model->version) . '</version>';
        $lines[] = "\t" . '<description>' . Xml::text($prefix . '_XML_DESCRIPTION') . '</description>';
        $lines[] = "\t" . '<namespace path="src">' . Xml::text($model->rootNamespace()) . '</namespace>';
        $lines[] = "\t" . '<files>';

        foreach ($this->folders($files) as $folder) {
            $lines[] = $folder === 'services'
                ? "\t\t" . '<folder plugin="' . Xml::attr($model->systemName) . '">services</folder>'
                : "\t\t" . '<folder>' . Xml::text($folder) . '</folder>';
        }

        $lines[] = "\t" . '</files>';
        $lines[] = "\t" . '<languages>';
        $lines[] = "\t\t" . '<language tag="en-GB">language/en-GB/' . Xml::attr($model->extensionName()) . '.ini</language>';
        $lines[] = "\t\t" . '<language tag="en-GB">language/en-GB/' . Xml::attr($model->extensionName()) . '.sys.ini</language>';
        $lines[] = "\t" . '</languages>';

        if ($model->params !== []) {
            $lines = array_merge($lines, $this->config($model));
        }

        $lines[] = '</extension>';

        $files->add($model->systemName . '.xml', implode("\n", $lines) . "\n");
    }

    /**
     * The folders the manifest has to install, read back from what was actually
     * generated.
     *
     * Derived rather than listed, so a type that contributes a forms/ or media/
     * folder needs no special case here: it appears in the manifest because the
     * files exist. This is why the pipeline lets the plugin type generate first.
     *
     * The language folder is left out: a manifest installs those through
     * <languages>, not as a plain folder.
     *
     * @param   FileCollection  $files  The files generated so far.
     *
     * @return  string[]  Folder names, sorted.
     *
     * @since   0.1.0
     */
    private function folders(FileCollection $files): array
    {
        $folders = [];

        foreach ($files->paths() as $path) {
            $slash = strpos($path, '/');

            if ($slash === false) {
                continue;
            }

            $folder = substr($path, 0, $slash);

            if ($folder !== 'language') {
                $folders[$folder] = true;
            }
        }

        $names = array_keys($folders);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * Render the <config> block holding the plugin's own parameters.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  string[]  The lines of the config block.
     *
     * @since   0.1.0
     */
    private function config(PluginModel $model): array
    {
        $prefix = $model->languagePrefix();
        $lines  = [];

        $lines[] = "\t" . '<config>';
        $lines[] = "\t\t" . '<fields name="params">';
        $lines[] = "\t\t\t" . '<fieldset name="basic">';

        foreach ($model->params as $param) {
            $name = (string) ($param['name'] ?? '');
            $type = (string) ($param['type'] ?? 'text');

            $lines[] = "\t\t\t\t" . '<field';
            $lines[] = "\t\t\t\t\t" . 'name="' . Xml::attr($name) . '"';
            $lines[] = "\t\t\t\t\t" . 'type="' . Xml::attr($type) . '"';
            $lines[] = "\t\t\t\t\t" . 'label="' . Xml::attr($prefix . '_FIELD_' . strtoupper($name) . '_LABEL') . '"';

            if (($param['default'] ?? '') !== '') {
                $lines[] = "\t\t\t\t\t" . 'default="' . Xml::attr($param['default']) . '"';
            }

            if (($param['filter'] ?? '') !== '') {
                $lines[] = "\t\t\t\t\t" . 'filter="' . Xml::attr($param['filter']) . '"';
            }

            $options = \is_array($param['options'] ?? null) ? $param['options'] : [];

            if ($options === []) {
                $lines[] = "\t\t\t\t\t" . '/>';

                continue;
            }

            $lines[] = "\t\t\t\t\t" . '>';

            foreach ($options as $value => $label) {
                $lines[] = "\t\t\t\t\t" . '<option value="' . Xml::attr($value) . '">' . Xml::text($label) . '</option>';
            }

            $lines[] = "\t\t\t\t" . '</field>';
        }

        $lines[] = "\t\t\t" . '</fieldset>';
        $lines[] = "\t\t" . '</fields>';
        $lines[] = "\t" . '</config>';

        return $lines;
    }
}
