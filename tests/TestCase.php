<?php

/**
 * The test base class.
 *
 * Under PHPUnit this is simply PHPUnit's TestCase. Without PHPUnit installed it
 * is a small shim implementing the handful of assertions these tests use, so the
 * suite can also be run with `php tests/run.php` on a machine that has nothing
 * but PHP. Tests are written against the PHPUnit API either way.
 *
 * Both declarations below sit inside a conditional, and that is load bearing.
 * PHP early-binds a class that has no parent and no interfaces: it is declared
 * when the file is compiled, before a single statement runs. Written at the top
 * level the shim would therefore exist before the `return` above it was reached,
 * class_alias would fail with "the name is already in use", and every test would
 * silently extend the shim instead of PHPUnit's TestCase - which PHPUnit reports
 * as "does not extend PHPUnit\Framework\TestCase" while `php tests/run.php` goes
 * on passing. A class declared inside an if block is bound at runtime instead,
 * so it is only declared when it is actually wanted.
 */

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests;

if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    class_alias(\PHPUnit\Framework\TestCase::class, __NAMESPACE__ . '\\TestCase');

    return;
}

// AssertionFailed extends RuntimeException, so it is late-bound and would be
// safe at the top level. It is kept here with the shim it belongs to.
if (!class_exists(__NAMESPACE__ . '\\AssertionFailed', false)) {
    class AssertionFailed extends \RuntimeException
    {
    }
}

if (!class_exists(__NAMESPACE__ . '\\TestCase', false)) {
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
}
