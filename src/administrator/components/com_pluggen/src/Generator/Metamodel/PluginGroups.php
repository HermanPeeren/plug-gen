<?php

/**
 * @package     Pluggen
 * @subpackage  Generator
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Generator\Metamodel;

/**
 * The plugin groups Joomla ships, with a readable label for each.
 *
 * This is the single source of truth for "which groups exist", used both by the
 * validator and by the type dropdown.
 *
 * Note what this list is and is not. A plugin group is only a folder name under
 * plugins/ and the "folder" column in #__extensions, and any extension may ship
 * its own group. So this list is a convenience for the interface, not a rule of
 * the metamodel: a group missing from it is unusual, not impossible.
 *
 * A group is also not the same thing as a generator type. Today the two map one
 * to one, but a group such as "content" holds skeletons as different as an event
 * bridge and a display plugin, so the metamodel keeps room for several types in
 * one group.
 *
 * @since  0.1.0
 */
final class PluginGroups
{
    /**
     * Group name => readable label, for the groups shipped with Joomla 6.1.
     *
     * @var    array<string, string>
     * @since  0.1.0
     */
    public const CORE = [
        'actionlog'          => 'Action Log',
        'api-authentication' => 'API Authentication',
        'authentication'     => 'Authentication',
        'behaviour'          => 'Behaviour',
        'captcha'            => 'Captcha',
        'content'            => 'Content',
        'editors'            => 'Editors',
        'editors-xtd'        => 'Editors XTD (editor buttons)',
        'extension'          => 'Extension',
        'fields'             => 'Fields',
        'filesystem'         => 'Filesystem',
        'finder'             => 'Finder (Smart Search)',
        'installer'          => 'Installer',
        'media-action'       => 'Media Action',
        'multifactorauth'    => 'Multi-factor Authentication',
        'privacy'            => 'Privacy',
        'quickicon'          => 'Quick Icon',
        'sampledata'         => 'Sample Data',
        'schemaorg'          => 'Schema.org',
        'system'             => 'System',
        'task'               => 'Task (scheduled tasks)',
        'user'               => 'User',
        'webservices'        => 'Web Services',
        'workflow'           => 'Workflow',
    ];

    /**
     * All known group names.
     *
     * @return  string[]  The group names.
     *
     * @since   0.1.0
     */
    public static function names(): array
    {
        return array_keys(self::CORE);
    }

    /**
     * Whether a name is one of the groups Joomla ships.
     *
     * @param   string  $group  The group name.
     *
     * @return  boolean  True when the group is known.
     *
     * @since   0.1.0
     */
    public static function isKnown(string $group): bool
    {
        return isset(self::CORE[$group]);
    }

    /**
     * The readable label for a group.
     *
     * @param   string  $group  The group name.
     *
     * @return  string  The label, or the group name itself when unknown.
     *
     * @since   0.1.0
     */
    public static function label(string $group): string
    {
        return self::CORE[$group] ?? $group;
    }
}
