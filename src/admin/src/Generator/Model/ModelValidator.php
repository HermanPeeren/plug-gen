<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Model;

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
final class ModelValidator
{
    private const ELEMENT_PATTERN   = '/^[a-z][a-z0-9_]{0,63}$/';
    private const CLASSNAME_PATTERN = '/^[A-Z][A-Za-z0-9_]{0,63}$/';
    private const NAMESPACE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/';
    private const VERSION_PATTERN   = '/^[0-9]+(\.[0-9]+){0,3}(-[A-Za-z0-9.]+)?$/';

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
     * @param   PluginModel  $model  The model to check.
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

        if (!preg_match(self::ELEMENT_PATTERN, $model->element)) {
            $errors[] = 'The plugin element must be lowercase, start with a letter and contain only letters, digits and underscores.';
        }

        if (!preg_match(self::CLASSNAME_PATTERN, $model->className)) {
            $errors[] = 'The class name must start with a capital and contain only letters, digits and underscores.';
        }

        if (!preg_match(self::NAMESPACE_PATTERN, $model->namespace)) {
            $errors[] = 'The namespace must be a backslash-separated list of PHP identifiers, with at least two parts.';
        }

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
     * Check a model and throw when it cannot be generated from.
     *
     * @param   PluginModel  $model  The model to check.
     *
     * @return  void
     *
     * @throws  ValidationException  When the model has any problem.
     *
     * @since   0.1.0
     */
    public function assertValid(PluginModel $model): void
    {
        $errors = $this->validate($model);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Check the chosen plugin type, and hand over to the type's own validation.
     *
     * @param   PluginModel  $model  The model to check.
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
