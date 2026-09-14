<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Service;

use Joomla\CMS\Application\CMSWebApplicationInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The CMS application as a user state store.
 *
 * This adapter is the only place in the component that knows user state lives on
 * the application object. The application is handed to it by the service
 * provider, which is the composition root and the one place allowed to know
 * where objects come from.
 *
 * User state lives on CMSWebApplicationInterface rather than on the broader
 * CMSApplicationInterface: a console application has no session to keep it in.
 *
 * @since  0.1.0
 */
final class ApplicationUserState implements UserStateInterface
{
    /**
     * Constructor.
     *
     * @param   CMSWebApplicationInterface  $app  The application holding the session state.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly CMSWebApplicationInterface $app)
    {
    }

    /**
     * Read a value from the user session state.
     *
     * @param   string  $key      The state key.
     * @param   mixed   $default  Returned when the key is not set.
     *
     * @return  mixed  The stored value, or the default.
     *
     * @since   0.1.0
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->app->getUserState($key, $default);
    }

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
    public function set(string $key, mixed $value): void
    {
        $this->app->setUserState($key, $value);
    }

    /**
     * Remove a value from the user session state.
     *
     * @param   string  $key  The state key.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function clear(string $key): void
    {
        $this->app->setUserState($key, null);
    }
}
