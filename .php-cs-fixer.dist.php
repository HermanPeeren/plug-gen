<?php

/**
 * php-cs-fixer configuration.
 *
 * PSR-12 plus a few rules the codebase already follows. Two things are kept out
 * of the fixer's reach on purpose:
 *
 * - src/admin/tmpl: Joomla layouts indent markup with tabs, and the fixer would
 *   convert them to spaces.
 * - tests/Fixtures/expected: generated output, compared byte for byte by the
 *   golden tests. Reformatting it would break the very thing it pins.
 *
 * Run it with `composer cs-fix`; it rewrites files, so read the diff.
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/tools', __DIR__ . '/build'])
    ->exclude(['Fixtures/expected'])
    ->notPath('#^admin/tmpl/#')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12'                        => true,
        'array_syntax'                  => ['syntax' => 'short'],
        'binary_operator_spaces'        => [
            'default'   => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal', '=' => 'align_single_space_minimal'],
        ],
        'no_unused_imports'             => true,
        'ordered_imports'               => ['sort_algorithm' => 'alpha'],
        'single_quote'                  => true,
        'trailing_comma_in_multiline'   => true,
        'no_trailing_whitespace'        => true,
        'single_line_empty_body'        => false,
    ])
    ->setFinder($finder);
