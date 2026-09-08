<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Output\ProtectedRegionMerger;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * Regenerating must not throw away hand-written code.
 *
 * This is the feature people will trust most, so it gets the round-trip test:
 * generate, edit inside a region, regenerate, and check that the edit survived
 * while the generated code around it was updated.
 */
final class ProtectedRegionTest extends TestCase
{
    public function testUserCodeSurvivesRegeneration(): void
    {
        $edited = <<<'PHP'
            <?php
            class Recipes
            {
                protected $layout = 'old';

                protected function index($item)
                {
                    // <pluggen id="index.custom">
                    $item->addTaxonomy('Cuisine', $item->cuisine);
                    // </pluggen>
                }
            }
            PHP;

        $regenerated = <<<'PHP'
            <?php
            class Recipes
            {
                protected $layout = 'new';

                protected function index($item)
                {
                    // <pluggen id="index.custom">
                    // </pluggen>
                }
            }
            PHP;

        $merged = (new ProtectedRegionMerger())->merge($edited, $regenerated);

        $this->assertStringContainsString("\$item->addTaxonomy('Cuisine', \$item->cuisine);", $merged);
        $this->assertStringContainsString("protected \$layout = 'new';", $merged);
        $this->assertStringNotContainsString("protected \$layout = 'old';", $merged);
    }

    public function testRegionsThatDisappearAreReportedNotSilentlyDropped(): void
    {
        $edited = "// <pluggen id=\"gone\">\n\$keepMe = 1;\n// </pluggen>\n";
        $fresh  = "// <pluggen id=\"other\">\n// </pluggen>\n";

        $merger = new ProtectedRegionMerger();
        $merger->merge($edited, $fresh);

        $this->assertSame(['gone'], $merger->orphanedRegions());
    }

    public function testEmptyRegionsDoNotOverwriteGeneratedDefaults(): void
    {
        $existing = "// <pluggen id=\"a\">\n// </pluggen>\n";
        $fresh    = "// <pluggen id=\"a\">\n\$generated = 1;\n// </pluggen>\n";

        $this->assertSame($fresh, (new ProtectedRegionMerger())->merge($existing, $fresh));
    }

    public function testFirstGenerationNeedsNoExistingFile(): void
    {
        $fresh = "// <pluggen id=\"a\">\n// </pluggen>\n";

        $this->assertSame($fresh, (new ProtectedRegionMerger())->merge('', $fresh));
    }
}
