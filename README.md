# com_pluggen

A Joomla 6 component that generates Joomla plugins from a saved model.

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

## Adding a plugin type

Drop a folder in `src/admin/src/Types/<Name>/`:

```
Definition.php        implements PluginTypeInterface
type.json             id, label, plugin group, targets
form.xml              the type-specific fieldset (fields carry showon="type_id:<id>")
templates/*.tpl       the files this type generates
```

Nothing else has to change: `TypeRegistry` discovers it, the edit form picks up the
fieldset, and the pipeline asks the definition which generators to run.

## Development

```
composer install          # phpunit
composer test             # unit tests
php build/build.php       # -> build/com_pluggen.zip
```

If PHPUnit is not installed, `php tests/run.php` runs the same test classes with a
zero-dependency runner.

## Security

Generation writes arbitrary PHP. The component therefore:

- never writes into `plugins/` — output is a ZIP, installed through Joomla's installer;
- requires the dedicated `core.generate` action, which is Super User equivalent;
- never `eval()`s or includes user-supplied code;
- validates every model value that reaches a path, and rejects traversal;
- routes all output through emitters that escape per target language.
