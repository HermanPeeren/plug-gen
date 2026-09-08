<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Emitter;

/**
 * Turns values into PHP source safely.
 *
 * Templates must never concatenate a model value into PHP source directly. A
 * plugin name containing a quote is the same bug class as SQL injection, one
 * target language over, so every value passes through here.
 */
final class PhpEmitter
{
    /** A PHP string literal, correctly quoted and escaped. */
    public static function string(mixed $value): string
    {
        return var_export((string) $value, true);
    }

    public static function bool(mixed $value): string
    {
        return $value ? 'true' : 'false';
    }

    public static function int(mixed $value): string
    {
        return (string) (int) $value;
    }

    /** A short array literal, recursively escaped. */
    public static function arrayLiteral(array $value, int $indent = 0): string
    {
        if ($value === []) {
            return '[]';
        }

        $pad    = str_repeat(' ', ($indent + 1) * 4);
        $close  = str_repeat(' ', $indent * 4);
        $isList = array_is_list($value);
        $parts  = [];

        foreach ($value as $key => $item) {
            $rendered = match (true) {
                \is_array($item) => self::arrayLiteral($item, $indent + 1),
                \is_bool($item)  => self::bool($item),
                \is_int($item)   => self::int($item),
                default          => self::string($item),
            };

            $parts[] = $isList ? $pad . $rendered : $pad . self::string($key) . ' => ' . $rendered;
        }

        return "[\n" . implode(",\n", $parts) . ",\n" . $close . ']';
    }

    /**
     * An identifier used verbatim in source (class name, method name, property).
     * Rejects anything that is not a plain PHP identifier rather than escaping it,
     * because there is no safe escaping for this position.
     */
    public static function identifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid PHP identifier.', $value));
        }

        return $value;
    }

    public static function namespaceName(string $value): string
    {
        $value = trim($value, '\\');

        foreach (explode('\\', $value) as $part) {
            self::identifier($part);
        }

        return $value;
    }

    /** Indent a block of user-supplied code to sit correctly inside a method body. */
    public static function indentBlock(string $code, int $levels = 2): string
    {
        $code = trim($code);

        if ($code === '') {
            return '';
        }

        $pad   = str_repeat(' ', $levels * 4);
        $lines = preg_split('/\R/', $code);

        return implode("\n", array_map(
            static fn(string $line): string => $line === '' ? '' : $pad . $line,
            $lines
        ));
    }

    /** Wrap text as a PHP comment block, neutralising any comment terminator in it. */
    public static function comment(string $text, int $levels = 1): string
    {
        $pad  = str_repeat(' ', $levels * 4);
        $text = str_replace('*/', '*\\/', $text);

        return implode("\n", array_map(
            static fn(string $line): string => rtrim($pad . ' * ' . $line),
            preg_split('/\R/', $text)
        ));
    }
}
