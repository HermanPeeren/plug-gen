# Developing Plug-gen

How Plug-gen is put together, how to work on it, and how it is released. What
the component is *for* is in the [README](../README.md).

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

## The edit form

Four tabs, whatever is installed:

| Tab | Holds |
|---|---|
| General | Everything shared by all plugin types, plus the injected services |
| Plugin Parameters | The parameters the generated plugin offers its own users |
| Type Settings | One `config_<type>` block per plugin type |
| Custom Code | One `slots_<type>` block per plugin type |

The last two hold every type at once. Each type's block is a single
non-repeating `subform` field carrying `showon="plugin_type:<id>"`, so the
browser shows the one belonging to the selected type and hides the rest. The
alternative - a tab per type - is two tabs per plugin type, which is fine at
three types and unreadable at twelve.

A non-repeating subform posts its children as `config_finder[context]`, exactly
the grouping `<fields name="config_finder">` produced before, so `ModelMapper`
did not change. What did change is the rendered ids, which gain a second
underscore: `jform_config_finder__context`. The Cypress specs select on those.

### What the form asks for, and what it works out

The system name is one lowercase word - no spaces, underscores or hyphens. It
has to be at once a folder name, the plugin element, the tail of every language
key, and capitalised a class name, and that is the only spelling legal in all
four at once.

From it and the plugin group, the model derives the class name
(`PluginModel::className()`) and the whole namespace
(`rootNamespace()`: `Acme\Plugin\Finder\Recipes`, group studly-cased as Joomla
does it). The form asks only for the organisation. A field the user can set is a
field they can set wrong, and a plugin whose namespace disagrees with its group
does not autoload - a failure with no message attached.

Answers that repeat across every plugin one organisation writes - the
organisation, the author, the copyright, whether to autoload the language file -
are component options, copied into a blueprint when it is created. Only then: a
saved blueprint owns its values, so editing an option later cannot change what
an existing blueprint generates.

### Injected services

Beside the fixed list of stock services, a blueprint can declare any number of
its own: a name, an expression that builds the value, and an optional import.
Each becomes one setter call in the generated provider, `name` capitalised into
`set<Name>()`. The expression is written out as typed - the same contract as the
custom code in a slot - while the name and the import are checked before
generation starts, because a malformed one produces a provider that does not
parse and the user meets that as a white page on their own site.

## The stored format

The model JSON carries `modelVersion`. It is at **1.1**, which renamed
`plugin.element` to `plugin.systemName` and replaced `plugin.namespace` and
`plugin.className` with `plugin.orgNamespace`.

`PluginModel::fromArray()` reads a 1.0 model and upgrades it on the way in, so a
blueprint saved before the change still opens; the upgraded model is written
back in the new shape the next time it is saved. A version this code does not
know is left as it is, so `ModelValidator` refuses it rather than misreading it.
`ModelFormatTest` pins all of that, including that the values 1.0 stored
explicitly come back identical from the derivation.

The blueprint table's `title` column became `name` at the same time, through
`sql/updates/mysql/0.4.2.sql`.

## Targets

The blueprint asks which Joomla version the output is for, and that choice
changes exactly one thing: how `services/provider.php` registers the plugin.

A Joomla 6 plugin is registered through `Container::lazy()`, which wraps the
factory in a lazy proxy, so the plugin object is only constructed when an event
it listens for is actually dispatched rather than on every request that imports
its group. `lazy()` arrived with joomla/di 3.1, which Joomla ships from 5.4
onwards, and core plugins adopted it in 6.1.

A Joomla 5 plugin gets the plain closure core used before that. This is not a
matter of taste: Joomla 5.0 to 5.3 carry joomla/di 3.0, where the method does
not exist, so `lazy()` there is a fatal "Call to undefined method" the first
time the plugin boots - and it boots during `importPlugin()`, which means the
whole page, not just the feature. `TargetTest` pins both variants.

Everything else is the same for both lines, which is worth stating because it
is easy to assume otherwise. The manifest does not differ: `php_minimum` and
`targetplatform` are update-server elements, not install-manifest ones, and
Joomla ignores them in an extension manifest. The generated plugin classes use
no syntax newer than PHP 8.1. `SubscriberInterface` and the service provider
work the same way in both.

One gap: each type declares a `targets` list in its `type.json`, and nothing
reads it. A type that could not support Joomla 5 has no way to say so yet.

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
2. Run `php build/update-xml.php`, which rewrites `updates.xml` from the
   manifest.
3. Commit both.
4. Create the tag `v<version>` and push it with the tag included. In PhpStorm:
   **Git > New Tag...**, then **Git > Push...** with *Push Tags* ticked.

Pushing the tag runs `.github/workflows/release.yml`, which checks the tag
against the manifest, runs the unit suite, builds `com_pluggen-<version>.zip`
and publishes it as a GitHub release with generated notes. Nothing is uploaded
by hand.

To build locally without releasing: `composer build`, or run `build/build.php`
from the Composer tool window in PhpStorm.

### The update server

An installed site learns about a new version from `updates.xml` in the root of
this repository, served raw by GitHub and pointed at by `<updateservers>` in the
manifest. It names the release asset by URL, so it has to be regenerated for
every version - step 2 above. Forgetting either hides the release from every
installed site or offers a download that 404s, and nothing else in the build
would notice, so the release workflow regenerates the file and fails when the
committed one differs.

`targetplatform` is a regular expression anchored at the start of the Joomla
version. It reads `6\.[0-9]+`, which offers the update on any Joomla 6 and on
nothing older: Plug-gen is written against Joomla 6, whatever version a
*generated plugin* targets. `client` is omitted because Joomla defaults it to
the administrator application, which is where a component belongs.

## Security

Generation writes arbitrary PHP. Plug-gen therefore:

- never writes into `plugins/`; output is a ZIP, installed through Joomla's installer;
- requires the dedicated `core.generate` action, which is Super User equivalent;
- never `eval()`s or includes user-supplied code;
- validates every model value that reaches a path, and rejects traversal;
- routes all output through emitters that escape per target language.
