<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Yepr\Component\Pluggen\Administrator\Service\UserStateInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Implemented by classes that read or write per-user session state.
 *
 * @since  0.1.0
 */
interface UserStateAwareInterface
{
    /**
     * Set the user state store.
     *
     * @param   UserStateInterface  $userState  The per-user state store.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setUserState(UserStateInterface $userState): void;
}
