<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Model;

use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Model\ValidationException;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\PluginGroups;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;

/**
 * Validates a model before anything is generated from it.
 *
 * Every value that can end up in a file path is checked here against a strict
 * allowlist. This is the boundary that keeps a model from writing outside its
 * own output directory, so the patterns are deliberately narrow: anything not
 * explicitly permitted is rejected, rather than sanitised and accepted.
 *
 * @since  0.1.0
 */
final class ModelValidator implements ValidatorInterface
{
    // The name is checked through what it becomes. Users write a name the way
    // they would write it anywhere - "Article update notification" - and the
    // class name and element are derived from it, so what has to be legal is
    // the derivation: a PHP identifier, and short enough to live in a path.
    private const CLASS_NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9]{0,63}$/';

    // The organisation part only, for example "Acme" or "Acme\Labs". The rest of
    // the namespace is derived, so there is nothing here to disagree with.
    private const ORG_NAMESPACE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';
    private const VERSION_PATTERN       = '/^[0-9]+(\.[0-9]+){0,3}(-[A-Za-z0-9.]+)?$/';

    /**
     * Constructor.
     *
     * @param   ?TypeRegistry  $types  The registry used to validate the chosen type;
     *                                 omit it to check only the generic parts.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly ?TypeRegistry $types = null)
    {
    }

    /**
     * Check a model and collect everything that is wrong with it.
     *
     * All problems are reported at once rather than failing on the first, so the
     * user can fix a form in one pass.
     *
     * @param   PluginModel     $model  The model to check.
     *
     * @return  string[]  The problems found; an empty array means the model is usable.
     *
     * @since   0.1.0
     */
    public function validate(PluginModel $model): array
    {
        $errors = [];

        if ($model->modelVersion !== PluginModel::CURRENT_VERSION) {
            $errors[] = \sprintf(
                'Unsupported model version "%s"; this generator writes version %s.',
                $model->modelVersion,
                PluginModel::CURRENT_VERSION
            );
        }

        if (!PluginGroups::isKnown($model->group)) {
            $errors[] = \sprintf('Unknown plugin group "%s".', $model->group);
        }

        if (trim($model->name) === '') {
            $errors[] = 'The plugin needs a name.';
        } elseif (!preg_match(self::CLASS_NAME_PATTERN, $model->className())) {
            $errors[] = \sprintf(
                'The name "%s" does not give a usable class name. It must contain letters or digits, '
                . 'and start with a letter.',
                $model->name
            );
        }

        if (!preg_match(self::ORG_NAMESPACE_PATTERN, $model->orgNamespace)) {
            $errors[] = 'The organisation namespace must be a PHP identifier, optionally backslash-separated, for example "Acme".';
        }

        $errors = array_merge($errors, $this->validateCustomServices($model));

        if (!preg_match(self::VERSION_PATTERN, $model->version)) {
            $errors[] = 'The version must look like 1.0.0.';
        }

        if ($model->authorEmail !== '' && !filter_var($model->authorEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The author email is not a valid address.';
        }

        if ($model->authorUrl !== '' && !$this->isSafeUrl($model->authorUrl)) {
            $errors[] = 'The author URL must be an http(s) address.';
        }

        foreach ($model->params as $index => $param) {
            if (!\is_array($param) || !preg_match('/^[a-z][a-z0-9_]*$/', (string) ($param['name'] ?? ''))) {
                $errors[] = \sprintf('Parameter %d has no valid name.', $index + 1);
            }
        }

        $errors = array_merge($errors, $this->validateType($model));

        return $errors;
    }

    /**
     * Check the freely declared services.
     *
     * The expression is written into the generated provider as typed, the way
     * custom code in a slot is. What is checked here is everything around it:
     * a setter name that is a PHP identifier, an expression that is not empty,
     * and an import that looks like a class name. A malformed name would
     * produce a provider that does not parse, and the user would meet that as
     * a white page on their own site rather than as a message here.
     *
     * @param   PluginModel     $model  The model to check.
     *
     * @return  string[]  The problems found.
     *
     * @since   0.4.2
     */
    private function validateCustomServices(PluginModel $model): array
    {
        $errors = [];
        $seen   = [];

        foreach ($model->customServices as $index => $service) {
            $position = $index + 1;

            if (!\is_array($service)) {
                $errors[] = \sprintf('Injected service %d is not a set of values.', $position);

                continue;
            }

            $name = (string) ($service['name'] ?? '');

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $name)) {
                $errors[] = \sprintf('Injected service %d has no valid name.', $position);
            } elseif (isset($seen[strtolower($name)])) {
                $errors[] = \sprintf('Injected service "%s" is named twice.', $name);
            } else {
                $seen[strtolower($name)] = true;
            }

            if (trim((string) ($service['expression'] ?? '')) === '') {
                $errors[] = \sprintf('Injected service %d has no expression saying how to build it.', $position);
            }

            $use = trim((string) ($service['use'] ?? ''));

            if ($use !== '' && !preg_match(self::ORG_NAMESPACE_PATTERN, trim($use, '\\'))) {
                $errors[] = \sprintf('Injected service %d has an import that is not a class name.', $position);
            }
        }

        return $errors;
    }

    /**
     * Check a model and throw when it cannot be generated from.
     *
     * @param   ModelInterface  $model  The model to check.
     *
     * @return  void
     *
     * @throws  ValidationException  When the model has any problem.
     *
     * @since   0.1.0
     */
    public function assertValid(ModelInterface $model): void
    {
        // The shared interface takes a ModelInterface and an implementation may
        // not ask for less. A model of another kind is not "valid by default":
        // it is the wrong thing entirely, and saying so here beats generating a
        // plugin out of it.
        if (!$model instanceof PluginModel) {
            throw new ValidationException(['This is not a plugin model.']);
        }

        $errors = $this->validate($model);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Check the chosen plugin type, and hand over to the type's own validation.
     *
     * @param   PluginModel     $model  The model to check.
     *
     * @return  string[]  The problems found.
     *
     * @since   0.1.0
     */
    private function validateType(PluginModel $model): array
    {
        if ($model->typeId === '') {
            return ['No plugin type selected.'];
        }

        if ($this->types === null) {
            return [];
        }

        if (!$this->types->has($model->typeId)) {
            return [\sprintf('Unknown plugin type "%s".', $model->typeId)];
        }

        $definition = $this->types->get($model->typeId);

        if ($definition->group() !== $model->group) {
            return [\sprintf(
                'Plugin type "%s" generates plugins in the "%s" group, not "%s".',
                $model->typeId,
                $definition->group(),
                $model->group
            )];
        }

        return $definition->validate($model);
    }

    /**
     * Whether a URL is one we are willing to write into a manifest.
     *
     * Only http and https: a javascript: or data: URL in an author field would
     * end up in generated markup.
     *
     * @param   string  $url  The URL to check.
     *
     * @return  boolean  True when the scheme is acceptable.
     *
     * @since   0.1.0
     */
    private function isSafeUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return \in_array(strtolower((string) $scheme), ['http', 'https'], true);
    }
}
