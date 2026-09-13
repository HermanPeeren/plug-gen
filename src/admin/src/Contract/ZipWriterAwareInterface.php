<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Contract;

use Yepr\Component\Pluggen\Administrator\Generator\Output\ZipWriter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Implemented by classes that turn a generated file set into an archive.
 *
 * @since  0.1.0
 */
interface ZipWriterAwareInterface
{
    /**
     * Set the zip writer.
     *
     * @param   ZipWriter  $zipWriter  The writer that persists a FileCollection.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setZipWriter(ZipWriter $zipWriter): void;
}
