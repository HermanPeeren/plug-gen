<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The MVC classes must receive their collaborators, not fetch or build them.
 *
 * Both rules are easy to state and easy to break by accident months later, so
 * they are checked mechanically rather than left to review. The service provider
 * is exempt on purpose: a composition root is the one place that is allowed to
 * know where objects come from.
 */
final class MvcDependencyInjectionTest extends TestCase
{
    /** Directories holding classes that must be dependency injected. */
    private const MVC_DIRECTORIES = [
        'Controller',
        'Model',
        'View',
        'Table',
        'Service',
        'Extension',
    ];

    /** Classes a generated or wired object may still instantiate itself. */
    private const ALLOWED_NEW = [
        // PHP's own classes are values, not collaborators.
        'DateTimeImmutable', 'DateTime', 'DateTimeZone', 'DateInterval',
        'ArrayIterator', 'ArrayObject', 'SplStack', 'SplQueue',
        'SimpleXMLElement',
        // Exceptions are created at the point they are thrown.
        'Exception', 'RuntimeException', 'InvalidArgumentException',
        'LogicException', 'OutOfBoundsException', 'DomainException',
    ];

    /**
     * Scanning is done over real code tokens, never over the raw text: a
     * docblock is allowed to mention Factory::getDate() while explaining why the
     * class does not call it.
     */
    public function testMvcClassesDoNotUseJoomlaFactory(): void
    {
        $offenders = [];

        foreach ($this->mvcFiles() as $file => $source) {
            foreach ($this->codeIdentifiers($source) as $identifier) {
                if ($identifier === 'Factory' || str_ends_with($identifier, '\\CMS\\Factory')) {
                    $offenders[] = basename($file);
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These MVC classes still reach for Joomla\\CMS\\Factory: ' . implode(', ', $offenders)
        );
    }

    /**
     * A collaborator built with new inside an MVC class is a dependency that was
     * never declared, and cannot be replaced in a test.
     */
    public function testMvcClassesDoNotBuildTheirOwnCollaborators(): void
    {
        $offenders = [];

        foreach ($this->mvcFiles() as $file => $source) {
            foreach ($this->instantiatedClasses($source) as $class) {
                $separator = strrpos($class, '\\');
                $short     = $separator === false ? $class : substr($class, $separator + 1);

                if (!\in_array($short, self::ALLOWED_NEW, true)) {
                    $offenders[] = basename($file) . ' -> new ' . $short;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These MVC classes build a collaborator instead of being given one: ' . implode(', ', $offenders)
        );
    }

    /**
     * Every class name that appears in executable code, ignoring comments and
     * string literals.
     *
     * @return string[]
     */
    private function codeIdentifiers(string $source): array
    {
        $names = [];

        foreach (token_get_all($source) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            if (\in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $names[] = trim($token[1], '\\');
            }
        }

        return $names;
    }

    /**
     * The classes instantiated with the new keyword in executable code.
     *
     * @return string[]
     */
    private function instantiatedClasses(string $source): array
    {
        $tokens  = token_get_all($source);
        $classes = [];
        $count   = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!\is_array($tokens[$i]) || $tokens[$i][0] !== T_NEW) {
                continue;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                if (\is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }

                if (\is_array($tokens[$j])
                    && \in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $classes[] = trim($tokens[$j][1], '\\');
                }

                // Anything else - new class(), new $variable - is not a named class.
                break;
            }
        }

        return $classes;
    }

    /** Everything the factory can inject must be declared through an interface. */
    public function testEveryAwareInterfaceIsUsedByTheFactory(): void
    {
        $factory = (string) file_get_contents(
            \dirname(PLUGGEN_TEST_ROOT) . '/src/admin/src/MVC/Factory/PluggenMVCFactory.php'
        );

        $contracts = glob(\dirname(PLUGGEN_TEST_ROOT) . '/src/admin/src/Contract/*AwareInterface.php') ?: [];

        $this->assertNotEmpty($contracts, 'No aware interfaces found.');

        foreach ($contracts as $contract) {
            $name = basename($contract, '.php');

            $this->assertStringContainsString(
                'instanceof ' . $name,
                $factory,
                $name . ' is declared but the factory never injects it.'
            );
        }
    }

    /** @return array<string, string> file path => source */
    private function mvcFiles(): array
    {
        $root  = \dirname(PLUGGEN_TEST_ROOT) . '/src/admin/src';
        $files = [];

        foreach (self::MVC_DIRECTORIES as $directory) {
            $path = $root . '/' . $directory;

            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $files;
    }
}
