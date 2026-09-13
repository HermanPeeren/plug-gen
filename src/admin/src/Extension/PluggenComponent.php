<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Extension;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The com_pluggen component.
 *
 * @since  0.1.0
 */
class PluggenComponent extends MVCComponent implements BootableExtensionInterface
{
    use HTMLRegistryAwareTrait;

    /**
     * Boot the extension.
     *
     * Nothing to set up: the component registers its services in the service
     * provider, which runs before this.
     *
     * @param   ContainerInterface  $container  The container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function boot(ContainerInterface $container)
    {
    }
}
