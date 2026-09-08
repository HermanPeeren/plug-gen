<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Emitter;

/**
 * Escaping for generated XML (manifests and form definitions).
 */
final class XmlEmitter
{
    /** Text content of an element. */
    public static function text(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** An attribute value, without the surrounding quotes. */
    public static function attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /** An element name, rejected outright when it is not a valid NCName. */
    public static function name(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.\-]*$/', $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid XML name.', $value));
        }

        return $value;
    }

    /**
     * Render an element with attributes, skipping attributes with an empty value.
     *
     * @param array<string, scalar|null> $attributes
     */
    public static function element(string $name, array $attributes = [], ?string $content = null, int $indent = 0): string
    {
        $pad  = str_repeat("\t", $indent);
        $tag  = self::name($name);
        $attr = '';

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $attr .= ' ' . self::name((string) $key) . '="' . self::attr($value) . '"';
        }

        if ($content === null || $content === '') {
            return $pad . '<' . $tag . $attr . '/>';
        }

        return $pad . '<' . $tag . $attr . '>' . self::text($content) . '</' . $tag . '>';
    }
}
