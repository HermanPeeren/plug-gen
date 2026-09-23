<?php

/**
 * @package     Pluggen
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Install script for Plug-gen.
 *
 * New at 4.1, and the reason it had to be. Until then this component carried
 * its own copy of the generation engine, so there was nothing to install beside
 * it; it reads the shared library now, and a site that installed the component
 * without one would fatal on the first screen that generates anything.
 *
 * Two jobs: refuse an environment the component cannot run in, and make sure
 * the shared library is there.
 *
 * The library carries the generation engine and the packages it needs, and is
 * shared with Exten-gen, Meta-gen, Gen-gen and Plug-gen so a site holds one copy
 * rather than one per extension. Joomla has no way for a package manifest to
 * declare a dependency on it, so the package carries a copy and installs it when the
 * site has none or has an older one. Regular Labs and Akeeba do the same, for
 * the same reason.
 *
 * The check runs on update as well as install: a site can be updated to a
 * version of Plug-gen that needs a newer library than the one already there.
 */
class Com_PluggenInstallerScript
{
    /**
     * The library this component cannot work without.
     */
    private const LIBRARY = 'yepr_gen';

    /**
     * The oldest library release that has everything this version calls.
     *
     * 0.10.0 because that is where the engine stood when 4.1 replaced this
     * component's private copy of it. The classes themselves - FileCollection,
     * the emitters, Pipeline, the renderer - have been there since 0.2.0, when
     * they were extracted from here, so the floor is a choice rather than a
     * requirement: there is no reason to certify this against an older library
     * than the one it was tested against.
     *
     * `composer.json` has to ask for the same thing, and `SharedEngineTest` is
     * what says the two agree. Let them drift and a site is handed a library
     * older than the code that was tested.
     */
    private const LIBRARY_MINIMUM = '0.10.0';

    /**
     * The oldest Joomla this runs on.
     *
     * Six. The output can still target Joomla 5 - that is what a model's
     * `target` says - but the component itself runs here, and 4.1 raised its
     * floor to PHP 8.3 with the shared library, which is Joomla 6's minimum
     * rather than Joomla 5's.
     *
     * @var string
     */
    private $minimumJoomlaVersion = '6.0';

    /**
     * The oldest PHP this runs on, which is Joomla 6's own minimum.
     *
     * @var string
     */
    private $minimumPHPVersion = '8.3';

    /**
     * Refuse an environment that cannot run this.
     *
     * @param   string            $type    install, update, discover_install or uninstall
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean  False stops the installation.
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            $this->say(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion), 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            $this->say(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion), 'error');

            return false;
        }

        return true;
    }

    /**
     * Put the shared library in place if it is missing or too old.
     *
     * @param   string            $type    install, update, discover_install or uninstall
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean  True, always: a failure here is reported rather than fatal.
     */
    public function postflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $installed = $this->installedLibraryVersion();

        if ($installed !== null && version_compare($installed, self::LIBRARY_MINIMUM, '>=')) {
            return true;
        }

        $directory = $parent->getParent()->getPath('source') . '/library';
        $archives  = is_dir($directory) ? (glob($directory . '/*.zip') ?: []) : [];

        if ($archives === []) {
            $this->say('The Yepr Gen library is not in this package, so it could not be installed.', 'warning');

            return true;
        }

        // The package carries the library as a zip, and Installer::install()
        // wants a directory with a manifest in it - handed the zip it reports
        // "Can't find XML setup file", which is true and unhelpful. Unpacking
        // first is what Joomla does everywhere it installs from an archive.
        $unpacked = InstallerHelper::unpack((string) $archives[0], true);

        if ($unpacked === false) {
            $this->say('The Yepr Gen library archive could not be unpacked.', 'warning');

            return true;
        }

        $installer = new Installer();
        $installer->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));

        // Not named $installed: that already holds the version this site had,
        // and the message below distinguishes an install from an update by it.
        $success = $installer->install($unpacked['extractdir']);

        // Whether it worked or not, the unpacked copy is temporary.
        InstallerHelper::cleanupInstall((string) $archives[0], $unpacked['extractdir']);

        if ($success) {
            $this->say(
                $installed === null
                    ? 'The Yepr Gen library was installed.'
                    : 'The Yepr Gen library was updated from ' . $installed . '.',
                'message'
            );

            return true;
        }

        // Not fatal. The component is installed; it simply will not generate
        // until the library is there, and saying so is more use than rolling
        // back everything the user just did.
        $this->say('The Yepr Gen library could not be installed. Plug-gen needs it in order to generate.', 'warning');

        return true;
    }

    /**
     * The version of the shared library this site has, or null when it has none.
     *
     * @return  string|null
     */
    private function installedLibraryVersion()
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $element = self::LIBRARY;

        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('library'))
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':element', $element, ParameterType::STRING);

        $db->setQuery($query);

        $manifest = $db->loadResult();

        if (!is_string($manifest) || $manifest === '') {
            return null;
        }

        $decoded = json_decode($manifest, true);

        return is_array($decoded) && isset($decoded['version']) ? (string) $decoded['version'] : null;
    }

    /**
     * Tell the user something, if there is anybody to tell.
     *
     * @param   string  $message  What happened.
     * @param   string  $type     message, warning or error.
     *
     * @return  void
     */
    private function say($message, $type)
    {
        $app = Factory::getApplication();

        if ($app) {
            $app->enqueueMessage($message, $type);
        }
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function install($parent): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function update($parent): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function uninstall($parent): bool
    {
        // The library is deliberately left in place. The other extensions in
        // the family share it, and removing something they depend on because
        // this one was uninstalled is how a working site breaks.
        return true;
    }
}
