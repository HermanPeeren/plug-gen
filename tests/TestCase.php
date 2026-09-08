<?php

/**
 * The test base class.
 *
 * Under PHPUnit this is simply PHPUnit's TestCase. Without PHPUnit installed it
 * is a small shim implementing the handful of assertions these tests use, so the
 * suite can also be run with `php tests/run.php` on a machine that has nothing
 * but PHP. Tests are written against the PHPUnit API either way.
 */

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests;

if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    class_alias(\PHPUnit\Framework\TestCase::class, __NAMESPACE__ . '\\TestCase');

    return;
}

class AssertionFailed extends \RuntimeException
{
}

abstract class TestCase
{
    public static int $assertions = 0;

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;

        if ($expected !== $actual) {
            throw new AssertionFailed(
                ($message !== '' ? $message . "\n" : '')
                . 'Expected: ' . self::describe($expected) . "\n"
                . 'Actual:   ' . self::describe($actual)
            );
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;

        if ($expected != $actual) {
            throw new AssertionFailed($message !== '' ? $message : 'Values are not equal.');
        }
    }

    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        $this->assertSame(true, $condition, $message);
    }

    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        $this->assertSame(false, $condition, $message);
    }

    protected function assertNotEmpty(mixed $value, string $message = ''): void
    {
        self::$assertions++;

        if (empty($value)) {
            throw new AssertionFailed($message !== '' ? $message : 'Value is empty.');
        }
    }

    protected function assertCount(int $expected, \Countable|array $haystack, string $message = ''): void
    {
        $this->assertSame($expected, \count($haystack), $message);
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        self::$assertions++;

        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(
                ($message !== '' ? $message . "\n" : '') . 'String does not contain: ' . $needle
            );
        }
    }

    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        self::$assertions++;

        if (str_contains($haystack, $needle)) {
            throw new AssertionFailed(
                ($message !== '' ? $message . "\n" : '') . 'String unexpectedly contains: ' . $needle
            );
        }
    }

    protected function fail(string $message): void
    {
        throw new AssertionFailed($message);
    }

    private static function describe(mixed $value): string
    {
        return \is_scalar($value) || $value === null
            ? var_export($value, true)
            : gettype($value);
    }
}
