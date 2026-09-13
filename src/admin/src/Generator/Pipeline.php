<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator;

use Yepr\Component\Pluggen\Administrator\Generator\Generators\GeneratorInterface;
use Yepr\Component\Pluggen\Administrator\Generator\Generators\LanguageGenerator;
use Yepr\Component\Pluggen\Administrator\Generator\Generators\ManifestGenerator;
use Yepr\Component\Pluggen\Administrator\Generator\Generators\ServiceProviderGenerator;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Component\Pluggen\Administrator\Generator\Output\FileCollection;

/**
 * Validates a model and runs every applicable generator over it.
 *
 * The shared generators run first, then the plugin type contributes its own
 * files. The result is held in memory; persisting it is somebody else's job.
 *
 * @since  0.1.0
 */
final class Pipeline
{
    /**
     * The generators that run for every plugin type.
     *
     * @var    GeneratorInterface[]
     * @since  0.1.0
     */
    private array $generators;

    /**
     * Constructor.
     *
     * @param   TypeRegistry       $types       The registry of plugin types.
     * @param   ?ModelValidator    $validator   The validator to run first; built from the registry when omitted.
     * @param   ?GeneratorInterface[]  $generators  The shared generators; the standard set when omitted.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly TypeRegistry $types,
        private readonly ?ModelValidator $validator = null,
        ?array $generators = null
    ) {
        // Order matters at one point only: the manifest inventories the folders
        // that were generated, so it runs after everything that creates them.
        $this->generators = $generators ?? [
            new ServiceProviderGenerator(),
            new LanguageGenerator($types),
            new ManifestGenerator(),
        ];
    }

    /**
     * A pipeline over the bundled types, for callers that have no container.
     *
     * Used by the fixture tool and the tests. Inside the component the pipeline
     * comes from the container instead, so no MVC class ever calls this.
     *
     * @return  self  A ready to use pipeline.
     *
     * @since   0.1.0
     */
    public static function default(): self
    {
        $types = TypeRegistry::default();

        return new self($types, new ModelValidator($types));
    }

    /**
     * Validate a model and run every applicable generator over it.
     *
     * @param   PluginModel  $model  The plugin model.
     *
     * @return  FileCollection  The generated files, in memory.
     *
     * @throws  Model\ValidationException  When the model cannot be generated from.
     *
     * @since   0.1.0
     */
    public function run(PluginModel $model): FileCollection
    {
        ($this->validator ?? new ModelValidator($this->types))->assertValid($model);

        $files = new FileCollection();

        // The plugin type goes first: the manifest lists the folders that were
        // actually generated, so a type contributing a forms/ or media/ folder
        // needs no special case in the shared generators.
        $this->types->get($model->typeId)->generate($model, $files);

        foreach ($this->generators as $generator) {
            if ($generator->supports($model)) {
                $generator->generate($model, $files);
            }
        }

        return $files;
    }
}
