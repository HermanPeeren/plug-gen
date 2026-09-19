<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The harness itself, because it failed silently once.
 *
 * tests/TestCase.php aliases PHPUnit's TestCase when PHPUnit is installed and
 * declares a shim when it is not. PHP early-binds a parentless class, so a shim
 * written at the top level of that file was declared at compile time, before the
 * early return above it ran: class_alias then failed and every test quietly
 * extended the shim. `php tests/run.php` kept passing, CI kept passing, and only
 * `composer test` complained - with a warning that named twelve test classes and
 * not the cause.
 *
 * A green suite that is not running under the harness it claims is worse than a
 * red one, so it gets asserted.
 */
final class TestHarnessTest extends TestCase
{
    public function testTestsRunUnderPhpunitWhenPhpunitIsInstalled(): void
    {
        if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
            // Running under php tests/run.php with no PHPUnit: the shim is
            // correct here, and that it works at all is what the suite proves.
            $this->assertTrue(true);

            return;
        }

        // class_parents() asks the runtime what this class actually inherits.
        // A static check cannot answer it: the alias is made by class_alias() at
        // runtime, so a static analyser reading tests/TestCase.php sees only the
        // shim - which is itself worth knowing, because it means the suite is
        // analysed against the shim's handful of assertions rather than
        // PHPUnit's.
        $ancestors = class_parents(static::class) ?: [];

        $this->assertTrue(
            isset($ancestors[\PHPUnit\Framework\TestCase::class]),
            'The test base class is not PHPUnit\'s TestCase. The shim in tests/TestCase.php '
            . 'has been declared instead of the alias, so these tests are not running under PHPUnit.'
        );
    }
}
