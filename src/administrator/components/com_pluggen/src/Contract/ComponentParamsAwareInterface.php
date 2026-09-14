<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Implemented by classes that need the component's own options.
 *
 * The setter is called by the component's MVCFactory. Reading the options with
 * ComponentHelper::getParams() would be a global lookup in the middle of a
 * model, which is exactly what this component's MVC classes do not do.
 *
 * @since  0.4.2
 */
interface ComponentParamsAwareInterface
{
    /**
     * Set the component options.
     *
     * @param   Registry  $params  The component options.
     *
     * @return  void
     *
     * @since   0.4.2
     */
    public function setComponentParams(Registry $params): void;
}
