<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Yepr\Component\Pluggen\Administrator\Service\ModelMapper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Implemented by classes that translate between the edit form and the model.
 *
 * @since  0.1.0
 */
interface ModelMapperAwareInterface
{
    /**
     * Set the form/model mapper.
     *
     * @param   ModelMapper  $mapper  The mapper between form data and the stored model.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setModelMapper(ModelMapper $mapper): void;
}
