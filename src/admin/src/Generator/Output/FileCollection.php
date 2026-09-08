<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Output;

/**
 * The result of a generation run: paths mapped to contents, held in memory.
 *
 * Generators write here and never touch the filesystem, which is what makes them
 * testable without a temp directory - and means a malformed model cannot write a
 * file anywhere, because at generation time there is no filesystem in play at all.
 *
 * Path containment is enforced on the way in rather than on the way out, so that
 * an invalid path fails at the generator that produced it.
 */
final class FileCollection implements \IteratorAggregate, \Countable
{
    /** @var array<string, string> */
    private array $files = [];

    public function add(string $path, string $contents): void
    {
        $path = self::normalise($path);

        if (isset($this->files[$path])) {
            throw new \LogicException(\sprintf('The file "%s" was generated twice.', $path));
        }

        $this->files[$path] = $contents;
    }

    public function replace(string $path, string $contents): void
    {
        $this->files[self::normalise($path)] = $contents;
    }

    public function has(string $path): bool
    {
        return isset($this->files[self::normalise($path)]);
    }

    public function get(string $path): string
    {
        $path = self::normalise($path);

        if (!isset($this->files[$path])) {
            throw new \OutOfBoundsException(\sprintf('No generated file "%s".', $path));
        }

        return $this->files[$path];
    }

    /** @return array<string, string> Sorted by path, so output is deterministic. */
    public function all(): array
    {
        $files = $this->files;
        ksort($files, SORT_STRING);

        return $files;
    }

    /** @return string[] */
    public function paths(): array
    {
        return array_keys($this->all());
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->all());
    }

    public function count(): int
    {
        return \count($this->files);
    }

    /**
     * Reduce a path to a safe, relative, forward-slashed form.
     *
     * Rejects absolute paths, drive letters, traversal and control characters.
     * Note that traversal is rejected, not resolved: a path that tries to climb
     * out is a bug in a generator, and silently rewriting it would hide that.
     */
    public static function normalise(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            throw new \InvalidArgumentException('An empty path cannot be generated.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new \InvalidArgumentException('Path contains control characters.');
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new \InvalidArgumentException(\sprintf('Path "%s" must be relative.', $path));
        }

        $segments = explode('/', $path);
        $clean    = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException(\sprintf('Path "%s" tries to leave the output directory.', $path));
            }

            $clean[] = $segment;
        }

        if ($clean === []) {
            throw new \InvalidArgumentException(\sprintf('Path "%s" does not name a file.', $path));
        }

        return implode('/', $clean);
    }
}
