<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ValidationException;
use Yepr\Component\Pluggen\Administrator\Generator\Output\ZipWriter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlueprintController extends FormController
{
    protected $view_list = 'blueprints';

    /**
     * Generate the plugin and send it as a zip.
     *
     * Deliberately a download and not a write into the plugins folder: the
     * generated code is executable, so installing it stays a separate, explicit
     * act through Joomla's installer, with its own permission check.
     */
    public function generate()
    {
        $this->checkToken();

        $app  = $this->app;
        $user = $app->getIdentity();

        // Generating writes PHP that will be executed once installed. This is a
        // deliberately separate permission from editing a blueprint.
        if (!$user->authorise('core.generate', 'com_pluggen')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_pluggen&view=blueprints', false));

            return false;
        }

        $id = (int) $app->getInput()->getInt('id');

        if ($id <= 0) {
            $app->enqueueMessage(Text::_('COM_PLUGGEN_ERR_NO_BLUEPRINT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_pluggen&view=blueprints', false));

            return false;
        }

        /** @var \Yepr\Component\Pluggen\Administrator\Model\BlueprintModel $model */
        $model = $this->getModel('Blueprint');

        try {
            $files = $model->generate($id);
        } catch (ValidationException $e) {
            foreach ($e->getErrors() as $error) {
                $app->enqueueMessage($error, 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_pluggen&task=blueprint.edit&id=' . $id, false));

            return false;
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::sprintf('COM_PLUGGEN_ERR_GENERATE_FAILED', $e->getMessage()), 'error');
            $this->setRedirect(Route::_('index.php?option=com_pluggen&task=blueprint.edit&id=' . $id, false));

            return false;
        }

        $item     = $model->getItem($id);
        $modelArr = (array) json_decode((string) $item->model, true);
        $plugin   = (array) ($modelArr['plugin'] ?? []);

        // Rebuilt from validated parts, never from user input directly.
        $name = 'plg_' . preg_replace('/[^a-z0-9_]/', '', (string) ($plugin['group'] ?? 'x'))
            . '_' . preg_replace('/[^a-z0-9_]/', '', (string) ($plugin['element'] ?? 'x'));

        $directory = rtrim($app->get('tmp_path'), '/\\') . \DIRECTORY_SEPARATOR . 'pluggen'
            . \DIRECTORY_SEPARATOR . bin2hex(random_bytes(8));
        $zipPath   = $directory . \DIRECTORY_SEPARATOR . $name . '.zip';

        (new ZipWriter())->write($files, $zipPath);

        $this->sendZip($zipPath, $name . '.zip');

        return true;
    }

    private function sendZip(string $zipPath, string $filename): void
    {
        $app = $this->app;

        $app->setHeader('Content-Type', 'application/zip', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $app->setHeader('Content-Length', (string) filesize($zipPath), true);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->sendHeaders();

        readfile($zipPath);

        // The archive exists only for the length of this request.
        @unlink($zipPath);
        @rmdir(\dirname($zipPath));

        $app->close();
    }
}
