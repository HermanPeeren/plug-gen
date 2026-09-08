<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Table;

use Joomla\CMS\Factory;
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
 */
class BlueprintTable extends Table implements CurrentUserInterface
{
    use CurrentUserTrait;

    protected $_supportNullValue = true;

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_pluggen.blueprint';

        parent::__construct('#__pluggen_blueprints', 'id', $db, $dispatcher);
    }

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

    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = $this->getCurrentUser();

        if ($this->id) {
            $this->modified    = $date;
            $this->modified_by = $user->id;
        } else {
            if (!(int) $this->created) {
                $this->created = $date;
            }

            if (empty($this->created_by)) {
                $this->created_by = $user->id;
            }
        }

        return parent::store($updateNulls);
    }
}
