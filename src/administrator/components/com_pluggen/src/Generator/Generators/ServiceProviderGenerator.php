<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
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
 *
 * This is the one generator whose output depends on the target. A Joomla 6
 * plugin is registered through Container::lazy(), which wraps the factory in a
 * lazy proxy so the plugin is only constructed when an event it listens for is
 * actually dispatched. That method does not exist in the DI container Joomla
 * 5.0 to 5.3 ship (joomla/di 3.0), so a Joomla 5 target gets the plain closure
 * core used before 6.1. Emitting lazy() for Joomla 5 would not degrade - it
 * would fatal with "Call to undefined method" the first time the plugin boots.
 *
 * @since  0.1.0
 */
final class ServiceProviderGenerator implements GeneratorInterface
{
    /**
     * The services that can be injected, as key => [use statement, setter line].
     *
     * @var    array<string, array{0: ?string, 1: string}>
     * @since  0.1.0
     */
    private const SERVICES = [
        'application'   => [null, '$plugin->setApplication(Factory::getApplication());'],
        'database'      => ['Joomla\\Database\\DatabaseInterface', '$plugin->setDatabase($container->get(DatabaseInterface::class));'],
        'dispatcher'    => ['Joomla\\Event\\DispatcherInterface', '$plugin->setDispatcher($container->get(DispatcherInterface::class));'],
        'mailerFactory' => [
            'Joomla\\CMS\\Mail\\MailerFactoryInterface',
            '$plugin->setMailerFactory($container->get(MailerFactoryInterface::class));',
        ],
        'userFactory'   => [
            'Joomla\\CMS\\User\\UserFactoryInterface',
            '$plugin->setUserFactory($container->get(UserFactoryInterface::class));',
        ],
    ];

    /**
     * Every plugin needs a service provider.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  boolean  Always true.
     *
     * @since   0.1.0
     */
    public function supports(PluginModel $model): bool
    {
        return true;
    }

    /**
     * Write services/provider.php.
     *
     * @param   PluginModel     $model  The plugin model.
     * @param   FileCollection  $files  The collection to add to.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function generate(PluginModel $model, FileCollection $files): void
    {
        $class     = Php::identifier($model->className());
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

        // Anything the stock list does not cover. The expression is the user's
        // own text, written out as typed - the same contract as the custom code
        // in a slot - while the setter name and the import are checked by
        // ModelValidator before generation is allowed to start.
        foreach ($model->customServices as $service) {
            if (!\is_array($service)) {
                continue;
            }

            $name       = (string) ($service['name'] ?? '');
            $expression = trim((string) ($service['expression'] ?? ''));
            $use        = trim(trim((string) ($service['use'] ?? '')), '\\');

            if ($name === '' || $expression === '') {
                continue;
            }

            if ($use !== '') {
                $uses[] = $use;
            }

            $setters[] = '$plugin->set' . ucfirst(Php::identifier($name)) . '(' . $expression . ');';
        }

        sort($uses, SORT_STRING);

        $body = [];
        $body[] = '<?php';
        $body[] = '';
        $body[] = '/**';
        $body[] = ' * @package     ' . str_replace('\\', '.', $model->rootNamespace());
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
        // Only the wrapper differs between the two targets; the closure body sits
        // at the same depth either way, as it does in core.
        $lazy   = $model->targetMajor() >= 6;
        $indent = '                ';

        $body[] = 'return new class () implements ServiceProviderInterface {';
        $body[] = '    public function register(Container $container)';
        $body[] = '    {';
        $body[] = '        $container->set(';
        $body[] = '            PluginInterface::class,';

        if ($lazy) {
            $body[] = '            $container->lazy(' . $class . '::class, function (Container $container) {';
        } else {
            $body[] = '            function (Container $container) {';
        }

        $body[] = $indent . '$plugin = new ' . $class . '(';
        $body[] = $indent . '    (array) PluginHelper::getPlugin(' . Php::string($model->group) . ', ' . Php::string($model->systemName) . ')';
        $body[] = $indent . ');';

        foreach ($setters as $setter) {
            $body[] = $indent . $setter;
        }

        $body[] = '';
        $body[] = $indent . 'return $plugin;';
        $body[] = $lazy ? '            })' : '            }';
        $body[] = '        );';
        $body[] = '    }';
        $body[] = '};';

        $files->add('services/provider.php', implode("\n", $body) . "\n");
    }
}
