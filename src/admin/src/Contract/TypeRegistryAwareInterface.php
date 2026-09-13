<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Implemented by classes that need the registry of plugin types.
 *
 * The setter is called by the component's MVCFactory, so no MVC class ever
 * builds a registry of its own.
 *
 * @since  0.1.0
 */
interface TypeRegistryAwareInterface
{
    /**
     * Set the plugin type registry.
     *
     * @param   TypeRegistry  $types  The registry of available plugin types.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setTypeRegistry(TypeRegistry $types): void;
}
