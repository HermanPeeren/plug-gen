<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Table;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\CMS\User\CurrentUserTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One saved plugin model.
 *
 * The current user arrives through CurrentUserTrait and the database through the
 * constructor, so this class needs no global lookup either. The timestamp is
 * built with PHP's own DateTimeImmutable rather than Joomla's Factory::getDate().
 *
 * @since  0.1.0
 */
class BlueprintTable extends Table implements CurrentUserInterface
{
    use CurrentUserTrait;

    /**
     * Indicates that columns fully support the NULL value in the database.
     *
     * @var    boolean
     * @since  0.1.0
     */
    protected $_supportNullValue = true;

    /**
     * Constructor.
     *
     * @param   DatabaseInterface     $db          The database driver.
     * @param   ?DispatcherInterface  $dispatcher  The event dispatcher for this table.
     *
     * @since   0.1.0
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_pluggen.blueprint';

        parent::__construct('#__pluggen_blueprints', 'id', $db, $dispatcher);
    }

    /**
     * Check the row before it is stored.
     *
     * @return  boolean  True when the row may be stored.
     *
     * @since   0.1.0
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            $this->setError(Text::_('COM_PLUGGEN_ERR_TABLE_TITLE'));

            return false;
        }

        // The model column must always hold decodable JSON: everything downstream
        // assumes it, and a half-written model is harder to diagnose later.
        if (!\is_string($this->model) || json_decode($this->model, true) === null) {
            $this->setError(Text::_('COM_PLUGGEN_ERR_TABLE_MODEL'));

            return false;
        }

        return true;
    }

    /**
     * Store the row, maintaining the created and modified bookkeeping.
     *
     * @param   boolean  $updateNulls  True to update fields even when they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   0.1.0
     */
    public function store($updateNulls = true)
    {
        $now  = $this->now();
        $user = $this->getCurrentUser();

        if ($this->id) {
            $this->modified    = $now;
            $this->modified_by = $user->id;
        } else {
            if (!(int) $this->created) {
                $this->created = $now;
            }

            if (empty($this->created_by)) {
                $this->created_by = $user->id;
            }
        }

        return parent::store($updateNulls);
    }

    /**
     * The current UTC time in the format Joomla stores datetimes in.
     *
     * PHP's own date classes are used rather than Joomla's Factory::getDate(),
     * so this class has no global dependency at all.
     *
     * @return  string  The timestamp, as "Y-m-d H:i:s" in UTC.
     *
     * @since   0.1.0
     */
    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
