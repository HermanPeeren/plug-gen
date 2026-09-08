<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Output;

/**
 * Carries hand-written code across a regeneration.
 *
 * Generated files mark editable areas:
 *
 *     // <pluggen id="index.custom">
 *     $item->addTaxonomy('Cuisine', $item->cuisine);
 *     // </pluggen>
 *
 * When a file is regenerated, the regions of the previous version are lifted out
 * by id and put back into the new one. Regions the new template no longer has are
 * reported rather than dropped quietly, because losing someone's code without
 * telling them is the worst thing a generator can do.
 */
final class ProtectedRegionMerger
{
    private const PATTERN = '~^([ \t]*)(?://|#|\*|<!--)?\s*<pluggen id="([A-Za-z0-9_.\-]+)">.*?\R(.*?)^[ \t]*(?://|#|\*|<!--)?\s*</pluggen>~ms';

    /** @var string[] Ids that existed in the old file but not in the new one. */
    private array $orphans = [];

    /**
     * @param string $existing   The file as it is on disk now (may be empty).
     * @param string $generated  The freshly generated file.
     */
    public function merge(string $existing, string $generated): string
    {
        $this->orphans = [];

        if (trim($existing) === '') {
            return $generated;
        }

        $kept = $this->extract($existing);

        if ($kept === []) {
            return $generated;
        }

        $used   = [];
        $merged = preg_replace_callback(
            self::PATTERN,
            static function (array $match) use ($kept, &$used): string {
                $id = $match[2];

                if (!isset($kept[$id])) {
                    return $match[0];
                }

                $used[$id] = true;

                // Keep the generated markers, swap only the body.
                $body  = rtrim($kept[$id], "\r\n");
                $open  = substr($match[0], 0, strpos($match[0], "\n") + 1);
                $close = substr($match[0], strrpos($match[0], "\n") + 1);

                return $open . ($body === '' ? '' : $body . "\n") . $close;
            },
            $generated
        );

        $this->orphans = array_values(array_diff(array_keys($kept), array_keys($used)));

        return $merged ?? $generated;
    }

    /** @return string[] */
    public function orphanedRegions(): array
    {
        return $this->orphans;
    }

    /** @return array<string, string> region id => body */
    public function extract(string $source): array
    {
        $regions = [];

        if (preg_match_all(self::PATTERN, $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $body = $match[3];

                if (trim($body) !== '') {
                    $regions[$match[2]] = $body;
                }
            }
        }

        return $regions;
    }

    /** Render an empty region for a template. */
    public static function region(string $id, string $body = '', int $levels = 2): string
    {
        $pad  = str_repeat(' ', $levels * 4);
        $body = rtrim($body, "\r\n");

        return $pad . '// <pluggen id="' . $id . '">' . "\n"
            . ($body === '' ? '' : $body . "\n")
            . $pad . '// </pluggen>';
    }
}
