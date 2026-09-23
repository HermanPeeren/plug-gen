<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Gen\Core\Emitter\IniEmitter;
use Yepr\Gen\Core\Emitter\PhpEmitter;
use Yepr\Gen\Core\Emitter\XmlEmitter;
use Yepr\Component\Pluggen\Administrator\Generator\Model\ModelValidator;
use Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The two ways a model can do damage: escaping out of a generated file, and
 * escaping out of the output directory.
 */
final class SecurityTest extends TestCase
{
    /** Every path a generator can produce must stay inside the output. */
    public function testTraversalPathsAreRejected(): void
    {
        $bad = [
            '../evil.php',
            'src/../../evil.php',
            '..\\evil.php',
            'src\\..\\..\\evil.php',
            '/etc/passwd',
            'C:/Windows/system32/evil.php',
            "src/evil\0.php",
        ];

        foreach ($bad as $path) {
            try {
                (new FileCollection())->add($path, '<?php');
                $this->fail('Path was accepted but should have been rejected: ' . $path);
            } catch (\InvalidArgumentException) {
                // Reaching here is the assertion. assertTrue(true) said the
                // same thing and said it to a static analyser as a tautology.
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNormalPathsSurviveNormalisation(): void
    {
        $this->assertSame('src/Extension/Recipes.php', FileCollection::normalise('src/Extension/Recipes.php'));
        $this->assertSame('src/Extension/Recipes.php', FileCollection::normalise('./src//Extension/Recipes.php'));
        $this->assertSame('src/Extension/Recipes.php', FileCollection::normalise('src\\Extension\\Recipes.php'));
    }

    /**
     * A quote in a model value must not break out of a PHP string literal.
     *
     * Tokenising is the real check: the rendered value has to be exactly one
     * string token, so there is no room for a statement to hide in it. Grepping
     * for the payload would be misleading, because the escaped form legitimately
     * still contains the payload characters.
     */
    public function testPhpStringsCannotBreakOut(): void
    {
        $hostile = [
            "'; system('rm -rf /'); //",
            '$var and ${injection}',
            "line\nbreak",
            'back\\slash',
            "null\0byte",
        ];

        // A null byte renders as a concatenation of literals rather than as one
        // token, so the rule is: nothing but string literals, dots and space.
        $allowed = [T_OPEN_TAG, T_CONSTANT_ENCAPSED_STRING, T_WHITESPACE];

        foreach ($hostile as $value) {
            $rendered = PhpEmitter::string($value);

            foreach (token_get_all('<?php ' . $rendered . ';') as $token) {
                $name = \is_array($token)
                    ? (\in_array($token[0], $allowed, true) ? null : token_name($token[0]))
                    : (\in_array($token, ['.', ';'], true) ? null : $token);

                if ($name !== null) {
                    $this->fail('Rendered literal contains executable syntax (' . $name . '): ' . $rendered);
                }
            }

            $this->assertSame($value, eval('return ' . $rendered . ';'));
        }
    }

    public function testPhpIdentifiersAreRejectedNotEscaped(): void
    {
        foreach (['Recipes; echo 1', '3Bad', 'has-dash', '', 'A B'] as $bad) {
            try {
                PhpEmitter::identifier($bad);
                $this->fail('Identifier was accepted but should have been rejected: ' . $bad);
            } catch (\InvalidArgumentException) {
                // Reaching here is the assertion. assertTrue(true) said the
                // same thing and said it to a static analyser as a tautology.
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testXmlValuesAreEscaped(): void
    {
        $this->assertSame('Ben &amp; Jerry', XmlEmitter::text('Ben & Jerry'));
        $this->assertSame('&lt;script&gt;', XmlEmitter::text('<script>'));
        $this->assertSame('a&quot;b', XmlEmitter::attr('a"b'));
    }

    /** A quote in a translation would otherwise break the whole language file. */
    public function testIniValuesAreEscaped(): void
    {
        $this->assertSame('KEY="a\\"b"', IniEmitter::line('key', 'a"b'));
        $this->assertSame('KEY="one two"', IniEmitter::line('key', "one\ntwo"));
    }

    /** Model validation is the gate; these must never reach a generator. */
    public function testHostileModelValuesAreRejected(): void
    {
        $cases = [
            'name of separators'     => ['name' => '../../'],
            'empty name'             => ['name' => '   '],
            'name of punctuation'    => ['name' => '- - -'],
            'name starting a digit'  => ['name' => '3 blind mice'],
            'unknown group'          => ['group' => 'evil'],
            'group traversal'        => ['group' => '../finder'],
            'namespace with code'    => ['orgNamespace' => 'Acme; echo 1'],
            'namespace with space'   => ['orgNamespace' => 'Acme Labs'],
            'javascript author url'  => ['authorUrl' => 'javascript:alert(1)'],
            'service without name'   => ['customServices' => [['expression' => '$container']]],
            'service name with code' => ['customServices' => [['name' => 'a()', 'expression' => '$c']]],
            'service without value'  => ['customServices' => [['name' => 'thing', 'expression' => '  ']]],
            'service bad import'     => ['customServices' => [['name' => 'thing', 'expression' => '$c', 'use' => 'A B']]],
        ];

        foreach ($cases as $label => $override) {
            $model  = PluginModel::fromArray($this->baseModel($override));
            $errors = (new ModelValidator())->validate($model);

            $this->assertNotEmpty($errors, 'Expected a validation error for: ' . $label);
        }
    }

    public function testTheBaseModelIsOtherwiseValid(): void
    {
        $this->assertSame([], (new ModelValidator())->validate(PluginModel::fromArray($this->baseModel())));
    }

    private function baseModel(array $override = []): array
    {
        $plugin = array_merge([
            'group'        => 'finder',
            'name'         => 'Recipes',
            'orgNamespace' => 'Acme',
            'version'      => '1.0.0',
        ], array_intersect_key(
            $override,
            array_flip(['group', 'name', 'orgNamespace', 'version', 'customServices'])
        ));

        if (isset($override['authorUrl'])) {
            $plugin['author'] = ['url' => $override['authorUrl']];
        }

        return [
            'modelVersion' => PluginModel::CURRENT_VERSION,
            'plugin'       => $plugin,
            'type'         => ['id' => 'finder', 'config' => []],
        ];
    }
}
