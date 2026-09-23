<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Target;

use Yepr\Component\Pluggen\Administrator\Generator\Generators\LanguageGenerator;
use Yepr\Component\Pluggen\Administrator\Generator\Generators\ManifestGenerator;
use Yepr\Component\Pluggen\Administrator\Generator\Generators\PluginTypeGenerator;
use Yepr\Component\Pluggen\Administrator\Generator\Generators\ServiceProviderGenerator;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Gen\Core\Target\TargetInterface;

/**
 * What a plugin model is generated into: a Joomla plugin.
 *
 * This is the answer the shared pipeline asks for - which generators run, in
 * what order, and what has to be true of a model first. It is everything the
 * private `Pipeline` held apart from the loop, and the loop is the library's
 * now.
 *
 * **"Target" means two things in this component and they are not the same.**
 * A model carries a `target` saying which Joomla it is generated *for* -
 * `joomla-6.0` or `joomla-5.0` - which decides whether the service provider
 * registers the plugin lazily. That is a property of the model. This is a
 * target in the library's sense: the kind of artefact produced. A plugin for
 * Joomla 5 and one for Joomla 6 come out of this same target, and a second one
 * here would mean generating something that is not a Joomla plugin at all.
 *
 * Order is part of the definition rather than an implementation detail. The
 * type goes first because the manifest inventories the folders that were
 * actually generated, so a type contributing `forms/` or `media/` needs no
 * special case anywhere else - and the manifest goes last for the same reason.
 *
 * @since  0.5.0
 */
final class PluginTarget implements TargetInterface
{
    /**
     * What a model must satisfy before this target will generate from it.
     *
     * Resolved once rather than on every call, so that asking a target what it
     * requires and generating through it cannot end up asking two different
     * objects.
     *
     * @var    ValidatorInterface
     * @since  0.5.0
     */
    private readonly ValidatorInterface $validator;

    /**
     * Constructor.
     *
     * @param   TypeRegistry         $types      The plugin types installed here.
     * @param   ?ValidatorInterface  $validator  What a model must satisfy; built from
     *                                           the registry when omitted.
     *
     * @since   0.5.0
     */
    public function __construct(
        private readonly TypeRegistry $types,
        ?ValidatorInterface $validator = null
    ) {
        $this->validator = $validator ?? new ModelValidator($types);
    }

    /**
     * A target over the bundled types, for callers that have no container.
     *
     * Used by the fixture tool and the tests. Inside the component both halves
     * come from the container instead, so no MVC class calls this - which is
     * what `MvcDependencyInjectionTest` is about.
     *
     * @return  self  A ready to use target.
     *
     * @since   0.5.0
     */
    public static function default(): self
    {
        $types = TypeRegistry::default();

        return new self($types, new ModelValidator($types));
    }

    /**
     * The stable identifier.
     *
     * @return  string  The id.
     *
     * @since   0.5.0
     */
    public function id(): string
    {
        return 'joomla-plugin';
    }

    /**
     * How this target is named to a person choosing one.
     *
     * @return  string  The label.
     *
     * @since   0.5.0
     */
    public function label(): string
    {
        return 'Joomla plugin';
    }

    /**
     * The generators that produce this target's files, in the order they run.
     *
     * @return  GeneratorInterface[]  The generators, in order.
     *
     * @since   0.5.0
     */
    public function generators(): array
    {
        return [
            new PluginTypeGenerator($this->types),
            new ServiceProviderGenerator(),
            new LanguageGenerator($this->types),
            new ManifestGenerator(),
        ];
    }

    /**
     * What has to be true of a model before generating a plugin from it.
     *
     * Never null, though the interface allows it: a target that asked nothing
     * would let a model whose name is a path fragment through to a generator,
     * and the validator is where that is refused.
     *
     * @return  ValidatorInterface  The validator.
     *
     * @since   0.5.0
     */
    public function validator(): ValidatorInterface
    {
        return $this->validator;
    }
}
