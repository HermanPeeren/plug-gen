<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\MVC\Factory;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\Input\Input;
use Psr\Log\LoggerInterface;
use Yepr\Component\Pluggen\Administrator\Contract\ModelMapperAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\ModelValidatorAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\PipelineAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\TypeRegistryAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\UserStateAwareInterface;
use Yepr\Component\Pluggen\Administrator\Contract\ZipWriterAwareInterface;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Component\Pluggen\Administrator\Generator\Output\ZipWriter;
use Yepr\Component\Pluggen\Administrator\Generator\Pipeline;
use Yepr\Component\Pluggen\Administrator\Service\ModelMapper;
use Yepr\Component\Pluggen\Administrator\Service\UserStateInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The component's MVC factory: it injects this component's own services.
 *
 * Joomla's MVCFactory already injects the framework's services (form factory,
 * dispatcher, database, user factory and so on). This subclass adds the
 * generator services on top, so that no controller, model or view ever uses the
 * new keyword for a collaborator, and none of them reaches for a global.
 *
 * Why setters and not the container's buildObject():
 * Container::buildObject() resolves constructor arguments from the container by
 * type, and has no API for runtime arguments. The MVCFactoryInterface contract
 * requires forwarding a $config array that callers supply at runtime - an
 * AdminController passes ['ignore_request' => true], for instance - and that
 * array would be lost. The autowiring factory proposed in joomla-cms#48193
 * solves this with new interfaces that do not exist in Joomla 6.1, so it is not
 * something a component can rely on yet. Explicit wiring costs one line per
 * dependency and stays readable.
 *
 * @since  0.1.0
 */
final class PluggenMVCFactory extends MVCFactory
{
    /**
     * Constructor.
     *
     * @param   string               $namespace   The extension namespace.
     * @param   TypeRegistry         $types       The registry of plugin types.
     * @param   ModelMapper          $mapper      The mapper between form data and model.
     * @param   Pipeline             $pipeline    The generation pipeline.
     * @param   ModelValidator       $validator   The model validator.
     * @param   ZipWriter            $zipWriter   The writer that persists a file set.
     * @param   UserStateInterface   $userState   The per-user state store.
     * @param   ?LoggerInterface     $logger      An optional logger.
     *
     * @since   0.1.0
     */
    public function __construct(
        string $namespace,
        private readonly TypeRegistry $types,
        private readonly ModelMapper $mapper,
        private readonly Pipeline $pipeline,
        private readonly ModelValidator $validator,
        private readonly ZipWriter $zipWriter,
        private readonly UserStateInterface $userState,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($namespace, $logger);
    }

    /**
     * Create a controller and give it the services it declares.
     *
     * @param   string                   $name    The name of the controller.
     * @param   string                   $prefix  The controller prefix.
     * @param   array                    $config  The controller configuration.
     * @param   CMSApplicationInterface  $app     The application.
     * @param   Input                    $input   The input object.
     *
     * @return  \Joomla\CMS\MVC\Controller\ControllerInterface|null
     *
     * @since   0.1.0
     */
    public function createController($name, $prefix, array $config, CMSApplicationInterface $app, Input $input)
    {
        $controller = parent::createController($name, $prefix, $config, $app, $input);

        if ($controller !== null) {
            $this->injectServices($controller);
        }

        return $controller;
    }

    /**
     * Create a model and give it the services it declares.
     *
     * @param   string  $name    The name of the model.
     * @param   string  $prefix  The model prefix.
     * @param   array   $config  The model configuration, forwarded unchanged.
     *
     * @return  \Joomla\CMS\MVC\Model\ModelInterface|null
     *
     * @since   0.1.0
     */
    public function createModel($name, $prefix = '', array $config = [])
    {
        $model = parent::createModel($name, $prefix, $config);

        if ($model !== null) {
            $this->injectServices($model);
        }

        return $model;
    }

    /**
     * Create a view and give it the services it declares.
     *
     * @param   string  $name    The name of the view.
     * @param   string  $prefix  The view prefix.
     * @param   string  $type    The view type.
     * @param   array   $config  The view configuration.
     *
     * @return  \Joomla\CMS\MVC\View\ViewInterface|null
     *
     * @since   0.1.0
     */
    public function createView($name, $prefix = '', $type = '', array $config = [])
    {
        $view = parent::createView($name, $prefix, $type, $config);

        if ($view !== null) {
            $this->injectServices($view);
        }

        return $view;
    }

    /**
     * Inject every service the object declares through an aware interface.
     *
     * Each dependency is asked for explicitly by the class that needs it, so a
     * class cannot quietly reach for something it never declared.
     *
     * @param   object  $object  A freshly created controller, model or view.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function injectServices(object $object): void
    {
        if ($object instanceof TypeRegistryAwareInterface) {
            $object->setTypeRegistry($this->types);
        }

        if ($object instanceof ModelMapperAwareInterface) {
            $object->setModelMapper($this->mapper);
        }

        if ($object instanceof PipelineAwareInterface) {
            $object->setPipeline($this->pipeline);
        }

        if ($object instanceof ModelValidatorAwareInterface) {
            $object->setModelValidator($this->validator);
        }

        if ($object instanceof ZipWriterAwareInterface) {
            $object->setZipWriter($this->zipWriter);
        }

        if ($object instanceof UserStateAwareInterface) {
            $object->setUserState($this->userState);
        }
    }
}
