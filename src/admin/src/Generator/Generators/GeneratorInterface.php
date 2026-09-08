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
 */
interface GeneratorInterface
{
    public function supports(PluginModel $model): bool;

    public function generate(PluginModel $model, FileCollection $files): void;
}
