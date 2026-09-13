<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Yepr\Component\Pluggen\Administrator\Extension\PluggenComponent;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Component\Pluggen\Administrator\Generator\Output\ZipWriter;
use Yepr\Component\Pluggen\Administrator\Generator\Pipeline;
use Yepr\Component\Pluggen\Administrator\Generator\Template\Renderer;
use Yepr\Component\Pluggen\Administrator\MVC\Factory\PluggenMVCFactory;
use Yepr\Component\Pluggen\Administrator\Service\ApplicationUserState;
use Yepr\Component\Pluggen\Administrator\Service\ModelMapper;
use Yepr\Component\Pluggen\Administrator\Service\UserStateInterface;

/**
 * The component's composition root.
 *
 * This file is the only place in the component that decides where an object
 * comes from. Everything else - controllers, models, views, generators - is
 * handed what it needs. That is why Factory::getApplication() appears here and
 * nowhere else: a service provider is allowed to know about the environment,
 * an MVC class is not.
 *
 * @since  0.1.0
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function register(Container $container)
    {
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Yepr\\Component\\Pluggen'));

        $this->registerGeneratorServices($container);
        $this->registerMVCFactory($container);

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new PluggenComponent($container->get(ComponentDispatcherFactoryInterface::class));

                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }

    /**
     * Register the generator core as container services.
     *
     * These have no Joomla dependencies at all, which is why they can be built
     * here from plain constructor arguments and unit tested without the CMS.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function registerGeneratorServices(Container $container): void
    {
        $container->share(
            Renderer::class,
            function (Container $container) {
                return new Renderer();
            }
        );

        $container->share(
            TypeRegistry::class,
            function (Container $container) {
                return new TypeRegistry(
                    JPATH_ADMINISTRATOR . '/components/com_pluggen/src/Types',
                    'Yepr\\Component\\Pluggen\\Administrator\\Types',
                    $container->get(Renderer::class)
                );
            }
        );

        $container->share(
            ModelValidator::class,
            function (Container $container) {
                return new ModelValidator($container->get(TypeRegistry::class));
            }
        );

        $container->share(
            Pipeline::class,
            function (Container $container) {
                return new Pipeline(
                    $container->get(TypeRegistry::class),
                    $container->get(ModelValidator::class)
                );
            }
        );

        $container->share(
            ModelMapper::class,
            function (Container $container) {
                return new ModelMapper($container->get(TypeRegistry::class));
            }
        );

        $container->share(
            ZipWriter::class,
            function (Container $container) {
                return new ZipWriter();
            }
        );

        $container->share(
            UserStateInterface::class,
            function (Container $container) {
                $app = Factory::getApplication();

                // User state needs a session, which only a web application has.
                // com_pluggen is an administrator component, so this holds - but
                // it is worth saying out loud rather than assuming.
                if (!$app instanceof CMSWebApplicationInterface) {
                    throw new \RuntimeException('com_pluggen needs a web application to keep user state.');
                }

                return new ApplicationUserState($app);
            }
        );
    }

    /**
     * Register the component's own MVC factory.
     *
     * Built by hand rather than through the core MVCFactory service provider,
     * because the factory needs this component's services as well as the
     * framework's. The framework setters below mirror what the core provider
     * does, so nothing an MVC class normally gets is lost.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function registerMVCFactory(Container $container): void
    {
        $container->set(
            MVCFactoryInterface::class,
            function (Container $container) {
                $factory = new PluggenMVCFactory(
                    '\\Yepr\\Component\\Pluggen',
                    $container->get(TypeRegistry::class),
                    $container->get(ModelMapper::class),
                    $container->get(Pipeline::class),
                    $container->get(ModelValidator::class),
                    $container->get(ZipWriter::class),
                    $container->get(UserStateInterface::class)
                );

                $factory->setFormFactory($container->get(FormFactoryInterface::class));
                $factory->setDispatcher($container->get(DispatcherInterface::class));
                $factory->setDatabase($container->get(DatabaseInterface::class));
                $factory->setSiteRouter($container->get(SiteRouter::class));
                $factory->setCacheControllerFactory($container->get(CacheControllerFactoryInterface::class));
                $factory->setUserFactory($container->get(UserFactoryInterface::class));
                $factory->setMailerFactory($container->get(MailerFactoryInterface::class));

                return $factory;
            }
        );
    }
};
