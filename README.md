# Plug-gen

Plug-gen, short for Plugin Generator, is a Joomla 6 component that generates
Joomla plugins from a saved model. A model here is what Model Driven Engineering
means by the word: a complete description of the thing you want — the plugin's
type, namespace, parameters, taxonomies and custom code — stated in the
vocabulary of the domain rather than in PHP, and precise enough that the
implementation can be derived from it. What a plugin *can* be at all is the
metamodel; one concrete description is a model, and the generators turn it into
code.

Note the collision with Joomla's own vocabulary: this is not a Model in the MVC
sense, the class that fetches and processes data for a view. That is one layer of one extension, while a model here describes an entire plugin — and if the plugin being generated ever had an MVC Model of its own, that Model would be part of what the model describes.

Forms collect everything needed to describe a plugin 
- general information shared by all plugin types, 
- plus a fieldset per plugin type

The result is stored as JSON. That JSON model is the single input to generation: generators turn it into a complete, installable plugin package.

## Layers

| Layer | Where | Depends on Joomla |
|---|---|---|
| Metamodel — what a plugin *can* be | `src/admin/src/Generator/Metamodel`, `src/admin/src/Types/*/form.xml` | no |
| Model — one concrete plugin description (the saved JSON) | `src/admin/src/Generator/Model` | no |
| Generators — pure `model → file set` | `src/admin/src/Generator/Generators` | no |
| Component — forms, storage, download | `src/admin/src/{Controller,Model,View,Table}` | yes |

The generator core deliberately contains **no Joomla imports at all**, so it can be unit
tested without bootstrapping the CMS. `tests/Unit/NoJoomlaDependencyTest.php` enforces that.

Generators never touch the filesystem. They write into an in-memory `FileCollection`;
a separate writer turns that into a ZIP. This is what makes the whole core testable.

## Layout

```
src/                      the installable component
  pluggen.xml             manifest
  admin/
    src/Generator/        framework-agnostic generator core
    src/Types/<Type>/     one self-contained bundle per plugin type
    src/{Controller,Model,View,Table,Extension}/
    forms/ language/ services/ sql/ tmpl/
tests/                    unit tests + golden fixtures
cypress/                  three end-to-end specs
build/build.php           assembles the installable zip
```

## Plugin types and plugin groups

The form asks for the plugin type once. There is no separate "group" field: a
Joomla plugin group and a generator type map one to one today, and two fields
that must agree are only a way for them to disagree. The stored model still
records `plugin.group`, derived from the chosen type, so a generator reading the
JSON never has to resolve a type to learn where the plugin belongs.

They are not the same idea, though, and the metamodel keeps them apart. A group
is a deployment fact — a folder under `plugins/`, what
`PluginHelper::importPlugin('finder')` loads. A type is one template set. The
`content` group alone holds an event bridge (`plg_content_finder`), a display
plugin (`plg_content_vote`) and a referential-integrity plugin
(`plg_content_joomla`); offering those as separate starting points would be three
types in one group. `PluginTypeAndGroupTest` pins the one-to-one assumption and
fails the day it stops holding, pointing at the form that then needs a second
choice.

The dropdown lists **every** plugin group. Groups without a type bundle are shown
disabled rather than hidden, so it is visible what the generator cannot write
yet. Disabling is a browser hint only — `BlueprintModel::save()` refuses an
unavailable type as well.

## Adding a plugin type

Drop a folder in `src/admin/src/Types/<Name>/`:

```
Definition.php        implements PluginTypeInterface
type.json             id, label, plugin group, targets
form.xml              the type-specific fieldset (fields carry showon="plugin_type:<id>")
templates/*.tpl       the files this type generates
```

Nothing else has to change: `TypeRegistry` discovers it, its group stops being
greyed out in the dropdown, the edit form picks up the fieldset, and the pipeline
asks the definition which generators to run.

## Development

```
composer install          # phpunit, phpstan, phpcs, php-cs-fixer
composer test             # unit tests
composer analyse          # phpstan, level 5
composer cs               # phpcs, see phpcs.xml.dist
composer cs-fix-dry       # php-cs-fixer, dry run with a diff
php build/build.php       # -> build/com_pluggen.zip
```

If PHPUnit is not installed, `php tests/run.php` runs the same test classes with a
zero-dependency runner.

### Joomla as reference material

Static analysis needs the Joomla classes Plug-gen extends. Unpack a Joomla
package into `/joomla` (git-ignored, listed under `scanDirectories` in
`phpstan.neon`):

```bash
curl -L -o joomla.zip https://github.com/joomla/joomla-cms/releases/download/6.1.3/Joomla_6.1.3-Stable-Full_Package.zip
unzip -q joomla.zip -d joomla && rm joomla.zip
```

Use the **full package**, not a git clone: the framework packages
(`Joomla\Database`, `Joomla\DI`, `Joomla\Event`) live in `libraries/vendor`,
which the repository does not carry.

One trap worth knowing: do not give PHPStan a `bootstrapFiles` entry that
registers an autoloader. Once an autoloader is present PHPStan resolves classes
through it instead of through the scanned files, and every Joomla base class
turns into "class not found" even though `/joomla` is scanned.

## Security

Generation writes arbitrary PHP. Plug-gen therefore:

- never writes into `plugins/` — output is a ZIP, installed through Joomla's installer;
- requires the dedicated `core.generate` action, which is Super User equivalent;
- never `eval()`s or includes user-supplied code;
- validates every model value that reaches a path, and rejects traversal;
- routes all output through emitters that escape per target language.
