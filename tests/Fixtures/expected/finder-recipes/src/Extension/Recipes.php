<?php

/**
 * @package     Acme.Plugin.Finder.Recipes
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Acme\Plugin\Finder\Recipes\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\QueryInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Smart Search adapter for com_recipes.
 */
final class Recipes extends Adapter implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * The contexts this adapter answers to.
     *
     * Front-end editing goes through a FormModel, so its context ends in ".form"
     * rather than in the item name - the same item, a different context string.
     * Core's own content adapter checks both, and so do we.
     */
    private const CONTEXTS = [
        'com_recipes.recipe',
        'com_recipes.form',
    ];

    /**
     * The plugin identifier. Must match the plugin element apart from capitals,
     * because pluginDisable() compares it against the element name.
     *
     * @var string
     */
    protected $context = 'Recipes';

    /**
     * The extension whose content is indexed.
     *
     * @var string
     */
    protected $extension = 'com_recipes';

    /**
     * The com_finder sublayout used to render a result.
     *
     * @var string
     */
    protected $layout = 'recipe';

    /**
     * The content type, registered automatically on instantiation.
     *
     * @var string
     */
    protected $type_title = 'Recipe';

    /**
     * The table holding the indexable items.
     *
     * @var string
     */
    protected $table = '#__recipes';

    /**
     * The column holding the published state.
     *
     * @var string
     */
    protected $state_field = 'published';

    /**
     * Load the language file on instantiation.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * The parent Adapter already subscribes to onStartIndex, onBeforeIndex,
     * onBuildIndex and onFinderGarbageCollection. These are the events that
     * plg_content_finder re-dispatches when a single item changes.
     */
    public static function getSubscribedEvents(): array
    {
        return array_merge(parent::getSubscribedEvents(), [
            'onFinderChangeState'         => 'onFinderChangeState',
            'onFinderAfterDelete'         => 'onFinderAfterDelete',
            'onFinderBeforeSave'          => 'onFinderBeforeSave',
            'onFinderAfterSave'           => 'onFinderAfterSave',
        ]);
    }

    /**
     * Runs once before an indexing run. Return false to abort.
     */
    protected function setup()
    {
        // <pluggen id="finder.setup">
        // </pluggen>
        return true;
    }

    /**
     * Remember the access level before the save, so a change can be detected.
     */
    public function onFinderBeforeSave(FinderEvent\BeforeSaveEvent $event): void
    {
        if ($event->getIsNew()) {
            return;
        }

        if (\in_array($event->getContext(), self::CONTEXTS, true)) {
            $this->checkItemAccess($event->getItem());
        }
    }

    /**
     * Reindex the saved item, repairing the index if its access level changed.
     */
    public function onFinderAfterSave(FinderEvent\AfterSaveEvent $event): void
    {
        $row = $event->getItem();

        if (\in_array($event->getContext(), self::CONTEXTS, true)) {
            if (!$event->getIsNew() && $this->old_access != $row->access) {
                $this->itemAccessChange($row);
            }

            $this->reindex($row->id);
        }
    }

    /**
     * Remove deleted items from the index.
     */
    public function onFinderAfterDelete(FinderEvent\AfterDeleteEvent $event): void
    {
        $context = $event->getContext();
        $table   = $event->getItem();

        if (\in_array($context, self::CONTEXTS, true)) {
            $this->remove($table->id);

            return;
        }

        // Deleted from the Smart Search index view rather than from the component.
        if ($context === 'com_finder.index') {
            $this->remove($table->link_id);
        }
    }

    /**
     * Publish, unpublish or archive from a list view - and un-index everything
     * when this plugin itself is disabled.
     */
    public function onFinderChangeState(FinderEvent\AfterChangeStateEvent $event): void
    {
        $context = $event->getContext();
        $value   = $event->getValue();

        if (\in_array($context, self::CONTEXTS, true)) {
            $this->itemStateChange($event->getPks(), $value);
        }

        if ($context === 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($event->getPks());
        }
    }

    /**
     * Turn one row from getListQuery() into an index entry.
     */
    protected function index(Result $item)
    {
        if (ComponentHelper::isEnabled($this->extension) === false) {
            return;
        }

        $item->setLanguage();
        $item->context = 'com_recipes.recipe';

        // <pluggen id="finder.index.before">
        // </pluggen>
        $registry     = new Registry($item->params);
        $item->params = clone ComponentHelper::getParams($this->extension, true);
        $item->params->merge($registry);

        $item->metadata = new Registry($item->metadata);

        // Runs onContentPrepare over the text before it is tokenised.
        $item->summary = Helper::prepareContent($item->summary, $item->params, $item);
        $item->body    = Helper::prepareContent($item->body, $item->params, $item);

        // The identity of the item in the index; garbage collection matches on it.
        $item->url = $this->getUrl($item->id, $this->extension, $this->layout);

        // The link a visitor follows. Use your component's RouteHelper here.
        $item->route = $item->url;

        $title = $this->getItemMenuTitle($item->url);

        if (!empty($title) && $this->params->get('use_menu_title', true)) {
            $item->title = $title;
        }

        $item->addInstruction(Indexer::META_CONTEXT, 'metakey');
        $item->addInstruction(Indexer::META_CONTEXT, 'metadesc');

        // Undeclared properties land in the Result's "elements" bag, which is
        // serialised into #__finder_links - the only way custom data reaches the
        // search result layout, because the row itself is never read again.
        // <pluggen id="finder.index.elements">
        $item->imageUrl   = $item->image ?: '';
        $item->imageAlt   = $item->title;
        $item->prep_time  = (int) $item->prep_time;
        $item->servings   = (int) $item->servings;
        $item->difficulty = $item->difficulty;
        // </pluggen>
        $item->state = $this->translateState($item->state);

        $item->addTaxonomy('Type', 'Recipe');
        $item->addTaxonomy('Language', $item->language);

        // <pluggen id="finder.index.taxonomies">
        // </pluggen>
        Helper::getContentExtras($item);
        Helper::addCustomFields($item, 'com_recipes.recipe');

        $this->indexer->index($item);
    }

    /**
     * The query that feeds the indexer. The aliases are the contract: the
     * indexer hydrates a Result straight from this row.
     */
    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();

        if ($query instanceof QueryInterface) {
            return $query;
        }

        $query = $db->createQuery()
            ->select(
                $db->quoteName(
                    [
                        'a.id',
                        'a.title',
                        'a.alias',
                        'a.description',
                        'a.instructions',
                        'a.published',
                        'a.access',
                        'a.language',
                        'a.params',
                        'a.metadata',
                        'a.metakey',
                        'a.metadesc',
                        'a.created',
                        'a.modified',
                        'a.image',
                        'a.prep_time',
                        'a.servings',
                        'a.difficulty',
                    ],
                    [
                        null,
                        null,
                        null,
                        'summary',
                        'body',
                        'state',
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        'start_date',
                        null,
                        null,
                        null,
                        null,
                        null,
                    ]
                )
            )
            ->from($db->quoteName($this->table, 'a'));

        // <pluggen id="finder.listquery">
        // </pluggen>
        return $query;
    }

    /**
     * The inherited version joins #__categories on a.catid. This content has no
     * categories, so the category columns are filled with nulls instead.
     */
    protected function getStateQuery()
    {
        $db = $this->getDatabase();

        return $db->createQuery()
            ->select($db->quoteName('a.id'))
            ->select($db->quoteName('a.' . $this->state_field, 'state'))
            ->select($db->quoteName('a.access'))
            ->select('NULL AS ' . $db->quoteName('cat_state'))
            ->select('NULL AS ' . $db->quoteName('cat_access'))
            ->from($db->quoteName($this->table, 'a'));
    }
}
