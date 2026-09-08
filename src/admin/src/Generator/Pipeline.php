<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
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
 */
final class Pipeline
{
    /** @var GeneratorInterface[] */
    private array $generators;

    public function __construct(
        private readonly TypeRegistry $types,
        private readonly ?ModelValidator $validator = null,
        ?array $generators = null
    ) {
        $this->generators = $generators ?? [
            new ManifestGenerator(),
            new ServiceProviderGenerator(),
            new LanguageGenerator(),
        ];
    }

    public static function default(): self
    {
        $types = TypeRegistry::default();

        return new self($types, new ModelValidator($types));
    }

    /**
     * @throws Model\ValidationException when the model cannot be generated from.
     */
    public function run(PluginModel $model): FileCollection
    {
        ($this->validator ?? new ModelValidator($this->types))->assertValid($model);

        $files = new FileCollection();

        foreach ($this->generators as $generator) {
            if ($generator->supports($model)) {
                $generator->generate($model, $files);
            }
        }

        $this->types->get($model->typeId)->generate($model, $files);

        return $files;
    }
}
