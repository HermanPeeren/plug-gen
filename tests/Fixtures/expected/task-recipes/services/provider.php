<?php

/**
 * @package     Acme.Plugin.Task.Recipes
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Acme\Plugin\Task\Recipes\Extension\Recipes;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Recipes::class, function (Container $container) {
                $plugin = new Recipes(
                    (array) PluginHelper::getPlugin('task', 'recipes')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));
                $plugin->setMailerFactory($container->get(MailerFactoryInterface::class));

                return $plugin;
            })
        );
    }
};
