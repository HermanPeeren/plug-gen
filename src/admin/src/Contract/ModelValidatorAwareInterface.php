<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Implemented by classes that validate a plugin model on its own, outside a
 * generation run.
 *
 * @since  0.1.0
 */
interface ModelValidatorAwareInterface
{
    /**
     * Set the model validator.
     *
     * @param   ModelValidator  $validator  The validator for plugin models.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setModelValidator(ModelValidator $validator): void;
}
