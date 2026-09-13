<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Metamodel;

use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;

/**
 * What a plugin type must be able to tell the generator.
 *
 * A type bundle is a folder containing this class, a type.json, the form fields
 * that are shown when this type is selected, and the templates for the files it
 * contributes. Adding a type means adding a folder; no core file changes.
 */
interface PluginTypeInterface
{
    /** Stable id, matching the folder name in lowercase, e.g. "finder". */
    public function id(): string;

    /** The Joomla plugin group this type generates into, e.g. "finder". */
    public function group(): string;

    /** Untranslated label, used when no language string is available. */
    public function label(): string;

    /** Absolute path to the bundle folder. */
    public function path(): string;

    /** Absolute path to the type-specific form, or null when the type has no extra fields. */
    public function formPath(): ?string;

    /**
     * The insertion points this type offers, as id => description. These become
     * the code fields in the form and the protected regions in the output.
     *
     * @return array<string, string>
     */
    public function slots(): array;

    /**
     * Type-specific validation, on top of the generic model validation.
     *
     * @return string[]
     */
    public function validate(PluginModel $model): array;

    /**
     * Language keys this type needs on top of the generic ones.
     *
     * A task plugin advertises its routines to the scheduler through language
     * constants, so which keys are needed follows from the model rather than
     * from a fixed list. Returned as key => English text.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  array<string, string>  Language key => text.
     *
     * @since   0.1.0
     */
    public function languageKeys(PluginModel $model): array;

    /** Contribute the files this type is responsible for. */
    public function generate(PluginModel $model, FileCollection $files): void;
}
