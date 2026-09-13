<?php

declare(strict_types=1);

namespace Yepr\Component\Pluggen\Tests\Unit;

use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\PluginGroups;
use Yepr\Component\Pluggen\Administrator\Generator\Metamodel\TypeRegistry;
use Yepr\Component\Pluggen\Tests\TestCase;

/**
 * The relationship between a Joomla plugin group and a generator type.
 *
 * The edit form asks for one of them and derives the other, which is only safe
 * while every group has at most one type. That assumption is pinned here rather
 * than left implicit: the day a second type joins an existing group, this test
 * fails and points at the form that has to grow a second choice.
 */
final class PluginTypeAndGroupTest extends TestCase
{
    public function testEveryGroupHasAtMostOneType(): void
    {
        $registry = TypeRegistry::default();
        $seen     = [];

        foreach ($registry->all() as $id => $type) {
            $group = $type->group();

            if (isset($seen[$group])) {
                $this->fail(\sprintf(
                    'The "%s" group now has two types ("%s" and "%s"). The single plugin_type field '
                    . 'in the blueprint form can no longer derive the group - it needs a second choice.',
                    $group,
                    $seen[$group],
                    $id
                ));
            }

            $seen[$group] = $id;
        }

        $this->assertNotEmpty($seen, 'No plugin types are registered at all.');
    }

    /** While the mapping is one to one, a type id and its group are the same name. */
    public function testTypeIdMatchesItsGroup(): void
    {
        foreach (TypeRegistry::default()->all() as $id => $type) {
            $this->assertSame($type->group(), $id, 'Type "' . $id . '" does not match its group.');
        }
    }

    public function testTypeCanBeLookedUpByGroup(): void
    {
        $registry = TypeRegistry::default();

        $this->assertTrue($registry->hasGroup('finder'));
        $this->assertSame('finder', $registry->forGroup('finder')->id());
        $this->assertFalse($registry->hasGroup('system'));
    }

    /**
     * Every known group is offered, and the ones without a bundle come back as
     * null so the form can show them disabled rather than hide them.
     */
    public function testAvailabilityCoversEveryKnownGroup(): void
    {
        $availability = TypeRegistry::default()->availability();

        foreach (PluginGroups::names() as $group) {
            $this->assertTrue(
                \array_key_exists($group, $availability),
                'The group "' . $group . '" is missing from the type dropdown.'
            );
        }

        $this->assertNotEmpty($availability['finder'], 'Finder should be selectable.');
        $this->assertNotEmpty($availability['task'], 'Task should be selectable.');
        $this->assertSame(null, $availability['workflow'], 'Workflow has no bundle yet, so it must be disabled.');
        $this->assertSame(null, $availability['content'], 'Content has no bundle yet, so it must be disabled.');
    }

    /**
     * Which groups can be chosen today. This list grows as bundles are added,
     * and the assertion is deliberately exact: a group becoming selectable is a
     * change worth noticing in a diff.
     */
    public function testTheSelectableGroups(): void
    {
        $selectable = array_keys(array_filter(TypeRegistry::default()->availability()));

        $this->assertSame(['finder', 'task'], $selectable);
    }

    public function testGroupListAndValidatorAgree(): void
    {
        // The validator rejects anything outside this list, so a group offered by
        // the form that the validator refuses would be a trap.
        foreach (PluginGroups::names() as $group) {
            $this->assertTrue(PluginGroups::isKnown($group));
        }

        $this->assertFalse(PluginGroups::isKnown('definitely-not-a-group'));
        $this->assertSame('Finder (Smart Search)', PluginGroups::label('finder'));
    }
}
