<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Emitter;

/**
 * Escaping for Joomla language files.
 *
 * Joomla ini files are parsed with parse_ini_string() in a mode where a value is
 * a double quoted string. A raw quote or newline in a translation therefore
 * breaks the whole file - and a broken language file fails silently, showing raw
 * keys in the interface, which is a miserable thing to debug.
 *
 * @since  0.1.0
 */
final class IniEmitter
{
    /**
     * Render one KEY="value" line.
     *
     * @param   string  $key    The language key.
     * @param   string  $value  The translation.
     *
     * @return  string  The escaped line, without a trailing newline.
     *
     * @since   0.1.0
     */
    public static function line(string $key, string $value): string
    {
        return self::key($key) . '="' . self::value($value) . '"';
    }

    /**
     * Normalise and check a language key.
     *
     * @param   string  $key  The language key, in any case.
     *
     * @return  string  The upper case key.
     *
     * @throws  \InvalidArgumentException  When the key is not a valid language key.
     *
     * @since   0.1.0
     */
    public static function key(string $key): string
    {
        $key = strtoupper($key);

        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid language key.', $key));
        }

        return $key;
    }

    /**
     * Escape a translation so it cannot break out of its quoted value.
     *
     * @param   string  $value  The raw translation.
     *
     * @return  string  The escaped value, without the surrounding quotes.
     *
     * @since   0.1.0
     */
    public static function value(string $value): string
    {
        // Collapse newlines: an ini value is a single line.
        $value = preg_replace('/\R+/', ' ', $value) ?? '';

        // A double quote inside a double quoted ini value is written as "_QQ_".
        $value = str_replace('"', '"_QQ_"', $value);

        // Strip control characters that would corrupt the file.
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    }

    /**
     * Render a comment line.
     *
     * @param   string  $text  The comment text.
     *
     * @return  string  The comment, collapsed onto one line.
     *
     * @since   0.1.0
     */
    public static function comment(string $text): string
    {
        return '; ' . (preg_replace('/\R+/', ' ', $text) ?? '');
    }
}
