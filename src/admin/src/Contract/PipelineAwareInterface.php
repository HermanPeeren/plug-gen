<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Yepr\Component\Pluggen\Administrator\Generator\Pipeline;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Implemented by classes that run the generation pipeline.
 *
 * @since  0.1.0
 */
interface PipelineAwareInterface
{
    /**
     * Set the generation pipeline.
     *
     * @param   Pipeline  $pipeline  The pipeline that turns a model into a file set.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setPipeline(Pipeline $pipeline): void;
}
