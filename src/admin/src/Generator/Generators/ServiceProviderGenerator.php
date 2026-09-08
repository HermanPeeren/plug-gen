<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Generators;

use Yepr\Component\Pluggen\Administrator\Generator\Emitter\PhpEmitter as Php;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;

/**
 * services/provider.php - the DI wiring.
 *
 * Which setters are emitted depends on the services the model asks for. The
 * database setter is not optional for every type: a finder adapter dies in its
 * own constructor without it, so a type definition can force it on.
 */
final class ServiceProviderGenerator implements GeneratorInterface
{
    /** service key => [use statement, setter line] */
    private const SERVICES = [
        'application'   => [null, '$plugin->setApplication(Factory::getApplication());'],
        'database'      => ['Joomla\\Database\\DatabaseInterface', '$plugin->setDatabase($container->get(DatabaseInterface::class));'],
        'dispatcher'    => ['Joomla\\Event\\DispatcherInterface', '$plugin->setDispatcher($container->get(DispatcherInterface::class));'],
        'mailerFactory' => ['Joomla\\CMS\\Mail\\MailerFactoryInterface', '$plugin->setMailerFactory($container->get(MailerFactoryInterface::class));'],
        'userFactory'   => ['Joomla\\CMS\\User\\UserFactoryInterface', '$plugin->setUserFactory($container->get(UserFactoryInterface::class));'],
    ];

    public function supports(PluginModel $model): bool
    {
        return true;
    }

    public function generate(PluginModel $model, FileCollection $files): void
    {
        $class     = Php::identifier($model->className);
        $namespace = Php::namespaceName($model->extensionNamespace());

        $uses = [
            $namespace . '\\' . $class,
            'Joomla\\CMS\\Extension\\PluginInterface',
            'Joomla\\CMS\\Factory',
            'Joomla\\CMS\\Plugin\\PluginHelper',
            'Joomla\\DI\\Container',
            'Joomla\\DI\\ServiceProviderInterface',
        ];

        $setters = [];

        foreach (self::SERVICES as $key => [$use, $setter]) {
            if (!$model->service($key)) {
                continue;
            }

            if ($use !== null) {
                $uses[] = $use;
            }

            $setters[] = $setter;
        }

        sort($uses, SORT_STRING);

        $body = [];
        $body[] = '<?php';
        $body[] = '';
        $body[] = '/**';
        $body[] = ' * @package     ' . str_replace('\\', '.', $model->namespace);
        $body[] = ' *';
        $body[] = ' * @license     GNU General Public License version 2 or later; see LICENSE.txt';
        $body[] = ' */';
        $body[] = '';
        $body[] = "\\defined('_JEXEC') or die;";
        $body[] = '';

        foreach ($uses as $use) {
            $body[] = 'use ' . $use . ';';
        }

        $body[] = '';
        $body[] = 'return new class () implements ServiceProviderInterface {';
        $body[] = '    public function register(Container $container)';
        $body[] = '    {';
        $body[] = '        $container->set(';
        $body[] = '            PluginInterface::class,';
        $body[] = '            $container->lazy(' . $class . '::class, function (Container $container) {';
        $body[] = '                $plugin = new ' . $class . '(';
        $body[] = '                    (array) PluginHelper::getPlugin(' . Php::string($model->group) . ', ' . Php::string($model->element) . ')';
        $body[] = '                );';

        foreach ($setters as $setter) {
            $body[] = '                ' . $setter;
        }

        $body[] = '';
        $body[] = '                return $plugin;';
        $body[] = '            })';
        $body[] = '        );';
        $body[] = '    }';
        $body[] = '};';

        $files->add('services/provider.php', implode("\n", $body) . "\n");
    }
}
