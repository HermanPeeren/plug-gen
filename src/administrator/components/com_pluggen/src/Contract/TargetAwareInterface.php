<?php

/**
 * @package     Pluggen
 * @subpackage  Contract
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Yepr\Gen\Core\Target\TargetInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something that needs to know what a model is generated into.
 *
 * The pipeline moved to the shared library at 4.1 and takes two things rather
 * than one: a model, and the target it goes into. The private pipeline held
 * both, which is why nothing had to be told the second - and is also why
 * generating a different kind of artefact would have meant a different
 * pipeline rather than a different target.
 *
 * So this is the other half of `PipelineAwareInterface`, and the two travel
 * together: a class that runs a generation needs both.
 *
 * @since  0.5.0
 */
interface TargetAwareInterface
{
    /**
     * Set the target a model is generated into.
     *
     * @param   TargetInterface  $target  Which generators run, in what order.
     *
     * @return  void
     *
     * @since   0.5.0
     */
    public function setTarget(TargetInterface $target): void;
}
