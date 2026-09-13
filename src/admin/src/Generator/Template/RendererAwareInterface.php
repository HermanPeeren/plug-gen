<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Template;

/**
 * Implemented by plugin type definitions that render templates.
 *
 * The registry hands the renderer over when it instantiates a bundle, so a type
 * definition does not build one of its own either.
 *
 * @since  0.1.0
 */
interface RendererAwareInterface
{
    /**
     * Set the template renderer.
     *
     * @param   Renderer  $renderer  The renderer for template files.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setRenderer(Renderer $renderer): void;
}
