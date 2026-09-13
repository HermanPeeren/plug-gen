<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Generators;

use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;

/**
 * A generator contributes files for one concern. It must be pure: same model in,
 * same bytes out, no filesystem, no clock, no randomness.
 *
 * @since  0.1.0
 */
interface GeneratorInterface
{
    /**
     * Whether this generator has anything to contribute for the given model.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  boolean  True when generate() should be called.
     *
     * @since   0.1.0
     */
    public function supports(PluginModel $model): bool;

    /**
     * Add this generator's files to the collection.
     *
     * @param   PluginModel     $model  The plugin model.
     * @param   FileCollection  $files  The collection to add to.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function generate(PluginModel $model, FileCollection $files): void;
}
