<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Template;

/**
 * Renders a template file with a set of variables.
 *
 * Templates are plain PHP, which keeps the component free of vendored template
 * engines. The template file itself is trusted code shipped with a type bundle;
 * the *variables* are not, which is why templates use the emitters for every
 * value they interpolate.
 *
 * A template is included in an isolated scope with no access to $this.
 */
final class Renderer
{
    public function render(string $templateFile, array $variables = []): string
    {
        if (!is_file($templateFile)) {
            throw new \RuntimeException(\sprintf('Template "%s" does not exist.', $templateFile));
        }

        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();

            try {
                include $__file;

                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();

                throw $e;
            }
        };

        $output = $render($templateFile, $variables);

        // Generated text files use LF and end with exactly one newline, so that
        // regenerating on another platform does not produce a diff.
        $output = str_replace(["\r\n", "\r"], "\n", $output);

        return rtrim($output, "\n") . "\n";
    }
}
