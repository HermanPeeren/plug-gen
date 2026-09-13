<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Per-user state that survives a redirect.
 *
 * A model that needs to recover the data of a failed save only needs these two
 * operations, not a whole application object. Depending on the narrow interface
 * keeps the model honest about what it actually uses, and makes it testable with
 * an array-backed double instead of a bootstrapped CMS.
 *
 * @since  0.1.0
 */
interface UserStateInterface
{
    /**
     * Read a value from the user session state.
     *
     * @param   string  $key      The state key, for example "com_pluggen.edit.blueprint.data".
     * @param   mixed   $default  Returned when the key is not set.
     *
     * @return  mixed  The stored value, or the default.
     *
     * @since   0.1.0
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Write a value to the user session state.
     *
     * @param   string  $key    The state key.
     * @param   mixed   $value  The value to store.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function set(string $key, mixed $value): void;

    /**
     * Remove a value from the user session state.
     *
     * @param   string  $key  The state key.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function clear(string $key): void;
}
