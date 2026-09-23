<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Generators;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Output\FileCollection;

/**
 * The files the chosen plugin type contributes.
 *
 * One generator that stands for all of them, because which type runs is a
 * property of the model and the shared pipeline asks a target for its
 * generators without seeing one. So the choice lives here, in the only place
 * that has both the registry and the model, and the target stays a fixed list.
 *
 * **This is what the private pipeline did inline**, and the reason it is worth
 * naming rather than losing: a type bundle is still just a folder with a
 * `Definition` in it, and adding one still changes no file here. The bridge is
 * this class alone - `PluginTypeInterface` keeps its own signature, so nothing
 * a type author writes had to learn about the shared library.
 *
 * It runs first. The manifest inventories the folders that were generated, so a
 * type contributing `forms/` or `media/` needs no special case in the shared
 * generators - which is only true if it has contributed them by then.
 *
 * @since  0.5.0
 */
final class PluginTypeGenerator implements GeneratorInterface
{
    /**
     * Constructor.
     *
     * @param   TypeRegistry  $types  The registry to look the model's type up in.
     *
     * @since   0.5.0
     */
    public function __construct(private readonly TypeRegistry $types)
    {
    }

    /**
     * Whether the model names a type this registry has.
     *
     * A model naming a type that is not installed is refused by the validator
     * before any of this runs. Answering false rather than throwing keeps that
     * the validator's job: a generator that threw here would report a missing
     * type half way through a run, with some files already made.
     *
     * @param   ModelInterface  $model  The source model.
     *
     * @return  boolean  True when there is a type to generate.
     *
     * @since   0.5.0
     */
    public function supports(ModelInterface $model): bool
    {
        return $model instanceof PluginModel && $this->types->has($model->typeId);
    }

    /**
     * Let the type contribute its files.
     *
     * @param   ModelInterface  $model  The source model.
     * @param   FileCollection  $files  The collection to add to.
     *
     * @return  void
     *
     * @since   0.5.0
     */
    public function generate(ModelInterface $model, FileCollection $files): void
    {
        if (!$model instanceof PluginModel) {
            return;
        }

        $this->types->get($model->typeId)->generate($model, $files);
    }
}
