<?php

/**
 * @package     Pluggen
 * @subpackage  com_pluggen
 *
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Pluggen\Administrator\Model;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list of stored blueprints.
 *
 * @since  0.1.0
 */
class BlueprintsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array                 $config   An array of configuration options.
     * @param   ?MVCFactoryInterface  $factory  The factory that created this model.
     *
     * @since   0.1.0
     */
    public function __construct($config = [], $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'type_id', 'a.type_id',
                'published', 'a.published',
                'created', 'a.created',
            ];
        }

        parent::__construct($config, $factory);
    }

    /**
     * Read the filter and list state from the request.
     *
     * @param   string  $ordering   The default ordering column.
     * @param   string  $direction  The default ordering direction.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    protected function populateState($ordering = 'a.title', $direction = 'asc')
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));
        $this->setState('filter.type_id', $this->getUserStateFromRequest($this->context . '.filter.type_id', 'filter_type_id', ''));
        $this->setState('filter.published', $this->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', ''));

        parent::populateState($ordering, $direction);
    }

    /**
     * Build a cache id that varies with the active filters.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  The store id.
     *
     * @since   0.1.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.type_id');
        $id .= ':' . $this->getState('filter.published');

        return parent::getStoreId($id);
    }

    /**
     * Build the query for the blueprint list.
     *
     * @return  \Joomla\Database\QueryInterface  The query.
     *
     * @since   0.1.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['a.id', 'a.title', 'a.type_id', 'a.published', 'a.created', 'a.checked_out', 'a.checked_out_time']))
            ->select($db->quoteName('uc.name', 'editor'))
            ->from($db->quoteName('#__pluggen_blueprints', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'uc'), $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'));

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.published') . ' = :published')
                ->bind(':published', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->whereIn($db->quoteName('a.published'), [0, 1]);
        }

        $typeId = (string) $this->getState('filter.type_id');

        if ($typeId !== '') {
            $query->where($db->quoteName('a.type_id') . ' = :typeid')
                ->bind(':typeid', $typeId);
        }

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $search = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where($db->quoteName('a.title') . ' LIKE :search')
                ->bind(':search', $search);
        }

        $query->order(
            $db->escape($this->state->get('list.ordering', 'a.title')) . ' '
            . $db->escape($this->state->get('list.direction', 'asc'))
        );

        return $query;
    }
}
