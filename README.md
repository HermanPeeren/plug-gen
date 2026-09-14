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

---

How the component is put together, how to work on it and how it is released:
[docs/development.md](docs/development.md).
