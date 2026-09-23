<?php

/**
 * The test base class.
 *
 * PHPUnit's, and only PHPUnit's. It used to be a conditional: PHPUnit when it
 * was installed, and a hand-written shim implementing the handful of assertions
 * these tests use when it was not, so the suite could run with `php
 * tests/run.php` on a machine holding nothing but PHP.
 *
 * 4.1 ended that. The generation engine is `yepr/generator-core` now rather
 * than a copy kept here, so there is no longer any suite to run without
 * composer - the classes under test refer to a library only composer puts on
 * disk. A shim for a runner that cannot work is worse than no shim: it goes on
 * compiling, and the comment explaining its early-binding subtleties goes on
 * describing a problem nobody can have.
 */

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
}
