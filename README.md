# Plug-gen

Plug-gen, short for Plugin Generator, is a Joomla 6 component that generates
Joomla plugins from a saved model. A model here is what Model Driven Engineering
means by the word: a complete description of the thing you want (the plugin's
type, namespace, parameters, taxonomies and custom code) stated in the
vocabulary of the domain rather than in PHP, and precise enough that the
implementation can be derived from it. What a plugin *can* be at all is the
metamodel; one concrete description is a model, and the generators turn it into
code.

Note the collision with Joomla's own vocabulary: this is not a Model in the MVC
sense, the class that fetches and processes data for a view. That is one layer of one extension, while a model here describes an entire plugin; and if the plugin being generated ever had an MVC Model of its own, that Model would be part of what the model describes.

Forms collect everything needed to describe a plugin 
- general information shared by all plugin types, 
- plus a fieldset per plugin type

The result is stored as JSON. That JSON model is the single input to generation: generators turn it into a complete, installable plugin package.

## Layers

Paths below are relative to `ADMIN`, which is
`src/administrator/components/com_pluggen`.

| Layer                                                   | Where | Depends on Joomla |
|---------------------------------------------------------|---|---|
| Metamodel: what a plugin *can* be                       | `ADMIN/src/Generator/Metamodel`, `ADMIN/src/Types/*/form.xml` | no |
| Model: one concrete plugin description (the saved JSON) | `ADMIN/src/Generator/Model` | no |
| Generators: pure `model → file set`                     | `ADMIN/src/Generator/Generators` | no |
| Component: forms, storage, download                     | `ADMIN/src/{Controller,Model,View,Table}` | yes |

The generator core deliberately contains **no Joomla imports at all**, so it can be unit
tested without bootstrapping the CMS. `tests/Unit/NoJoomlaDependencyTest.php` enforces that.

Generators never touch the filesystem. They write into an in-memory `FileCollection`;
a separate writer turns that into a ZIP. This is what makes the whole core testable.

## Layout

`src/` mirrors the folder layout of a Joomla installation, so every file sits at
the path it will occupy on the site. The manifest's `folder=` attributes then
name real directories rather than translating between two layouts, and a new
part of the component - site code, media - is a folder in the obvious place
rather than a decision.

```
src/                                            the installable component
  pluggen.xml                                   manifest
  administrator/components/com_pluggen/
    src/Generator/                              framework-agnostic generator core
    src/Types/<Type>/                           one self-contained bundle per plugin type
    src/{Controller,Model,View,Table,Extension}/
    forms/ language/ services/ sql/ tmpl/
  components/com_pluggen/                       site code, when there is any
  media/com_pluggen/                            css and js, when there is any
tests/                                          unit tests + golden fixtures
cypress/                                        end-to-end specs
build/build.php                                 assembles the installable zip
```

The last two are not there yet; the manifest carries the blocks they will need
as a comment. The build script copies whatever is under `src/`, so adding them
is a matter of writing files and uncommenting.

## Plugin types and plugin groups

The form asks for the plugin type once. There is no separate "group" field: a
Joomla plugin group and a generator type map one to one today, and two fields
that must agree are only a way for them to disagree. The stored model still
records `plugin.group`, derived from the chosen type, so a generator reading the
JSON never has to resolve a type to learn where the plugin belongs.

They are not the same idea, though, and the metamodel keeps them apart. A group
is a deployment fact: a folder under `plugins/`, what
`PluginHelper::importPlugin('finder')` loads. A type is one template set. The
`content` group alone holds an event bridge (`plg_content_finder`), a display
plugin (`plg_content_vote`) and a referential-integrity plugin
(`plg_content_joomla`); offering those as separate starting points would be three
types in one group. `PluginTypeAndGroupTest` pins the one-to-one assumption and
fails the day it stops holding, pointing at the form that then needs a second
choice.

The dropdown lists **every** plugin group. Groups without a type bundle are shown
disabled rather than hidden, so it is visible what the generator cannot write
yet. Disabling is a browser hint only: `BlueprintModel::save()` refuses an
unavailable type as well.

## The plugin types that ship

In the order of the Joomla Community Magazine series on custom plugins.

**Task**: routines for the Task Scheduler.
([Custom Plugins, part 2](https://magazine.joomla.org/issues/2026/may-2026/custom-plugins-part-2-task-plugin))
A task plugin is wired by convention: three events point at `TaskPluginTrait`,
and a `TASKS_MAP` constant tells the trait where everything is. So the model
carries the routines (id, method, title, parameters and body) and the
generator derives the map, the handler methods, a parameter form per routine,
and the language constants the scheduler advertises them under. One plugin can
offer several routines, as `plg_task_sitestatus` does in core.

**Workflow**: actions that run at a transition.
([Custom Plugins, part 3](https://magazine.joomla.org/issues/2026/july-2026/custom-plugins-part-3-workflow-plugin))
Users trigger transitions and transitions trigger actions; this plugin supplies
the actions. The model carries the supported contexts and the action fields, and
the generator writes `forms/action.xml`, reads the values back from
`$transition->options` after the transition, and (importantly) overrides
`isSupported()`, which returns false in `WorkflowPluginTrait` and is the usual
reason a workflow plugin appears to do nothing at all.

**Finder**: a Smart Search adapter. (Custom Plugins, part 4; link to follow on
publication.) Configured almost entirely by class properties, so the model
carries the table, the column-to-alias mapping, the taxonomies and whether the
content has categories.

## Adding a plugin type

Drop a folder in `ADMIN/src/Types/<Name>/`:

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

If PHPUnit is not installed, `php tests/run.php` runs the same test classes with a zero-dependency runner.

### Joomla as reference material

Static analysis needs the Joomla classes Plug-gen extends. Unpack a Joomla
package into `/joomla` (git-ignored, listed under `scanDirectories` in
`phpstan.neon`):

```bash
# Newest stable full package of the Joomla 6 line
url=$(curl -s 'https://api.github.com/repos/joomla/joomla-cms/releases?per_page=50' \
  | grep -o 'https://github.com/joomla/joomla-cms/releases/download/6\.[0-9.]*/Joomla_6\.[0-9.]*-Stable-Full_Package\.zip' \
  | head -1)
curl -L -o joomla.zip "$url"
unzip -q joomla.zip -d joomla && rm joomla.zip
```

There is no static "latest" URL to point at: `downloads.joomla.org/latest` is an
HTML page rather than a file, and the version-shaped paths on that host all need
the exact version number. The releases API is the machine-readable answer.

Note the `6\.` in that pattern. It pins the **major line**, not merely the newest
release, and that matters: Joomla tags 5.x and 6.x in the same repository and
ships them on the same days - 5.4.8 and 6.1.3 were both published on 18 August
2026 - so GitHub's own `/releases/latest`, which is no more than the most
recently tagged non-prerelease, hands back a 5.x patch as soon as one lands after
a 6.x one. That would quietly fill `/joomla` with the wrong major version and
have PHPStan check this component against it. Change the `6` when the component
targets a newer line.

Joomla's own update stream is the wrong tool here: it serves `Update_Package`
archives, for upgrading a site rather than installing one, and as of 6.1.3
`update.joomla.org/core/list.xml` carries no 6.x entries at all.

Use the **full package**, not a git clone: the framework packages
(`Joomla\Database`, `Joomla\DI`, `Joomla\Event`) live in `libraries/vendor`,
which the repository does not carry.

One trap worth knowing: do not give PHPStan a `bootstrapFiles` entry that
registers an autoloader. Once an autoloader is present PHPStan resolves classes
through it instead of through the scanned files, and every Joomla base class
turns into "class not found" even though `/joomla` is scanned.

### Releasing

The version lives in one place: `<version>` in `src/pluggen.xml`. The build reads
it, names the package after it, and the release workflow refuses a tag that
disagrees with it.

1. Edit `<version>` in `src/pluggen.xml` (and `package.json`, which is cosmetic
   but easier to keep in step than to explain later).
2. Commit.
3. Create the tag `v<version>` and push it with the tag included. In PhpStorm:
   **Git > New Tag...**, then **Git > Push...** with *Push Tags* ticked.

Pushing the tag runs `.github/workflows/release.yml`, which checks the tag
against the manifest, runs the unit suite, builds `com_pluggen-<version>.zip`
and publishes it as a GitHub release with generated notes. Nothing is uploaded
by hand.

To build locally without releasing: `composer build`, or run `build/build.php`
from the Composer tool window in PhpStorm.

## Security

Generation writes arbitrary PHP. Plug-gen therefore:

- never writes into `plugins/`; output is a ZIP, installed through Joomla's installer;
- requires the dedicated `core.generate` action, which is Super User equivalent;
- never `eval()`s or includes user-supplied code;
- validates every model value that reaches a path, and rejects traversal;
- routes all output through emitters that escape per target language.
