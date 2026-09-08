<?php
/**
 * Template for a Smart Search adapter.
 *
 * @var \Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel $m
 * @var callable $str     PHP string literal
 * @var callable $id      validated PHP identifier
 * @var callable $block   indent a block of user code
 * @var callable $region  render a protected region, pre-filled from the model slot
 */

$context       = (string) $m->config('context');
$extension     = (string) $m->config('extension');
$table         = (string) $m->config('table');
$layout        = (string) $m->config('layout', strtolower($m->element));
$typeTitle     = (string) $m->config('typeTitle', ucfirst($m->element));
$stateField    = (string) $m->config('stateField', 'state');
$hasCategories = (bool) $m->config('hasCategories', false);
// The context is <extension>.<model name>, and the model name is the component's,
// not the plugin's: com_recipes has a RecipeModel, so the context is
// "com_recipes.recipe" while the plugin element is "recipes".
$itemName    = (string) $m->config('itemName');
$itemContext = $extension . '.' . $itemName;
$columns       = (array) $m->config('columns', []);
$taxonomies    = (array) $m->config('taxonomies', ['Type']);

// Back-end editing and front-end editing produce different context strings.
$contexts = [$itemContext, $extension . '.form'];

echo "<?php\n";
?>

/**
 * @package     <?= str_replace('\\', '.', $m->namespace) ?>

 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace <?= $m->extensionNamespace() ?>;

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
 * Smart Search adapter for <?= $extension ?>.
 */
final class <?= $id($m->className) ?> extends Adapter implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * The contexts this adapter answers to.
     *
     * Front-end editing goes through a FormModel, so its context ends in ".form"
     * rather than in the item name - the same item, a different context string.
     * Core's own content adapter checks both, and so do we.
     */
    private const CONTEXTS = <?= $arr($contexts, 1) ?>;

    /**
     * The plugin identifier. Must match the plugin element apart from capitals,
     * because pluginDisable() compares it against the element name.
     *
     * @var string
     */
    protected $context = <?= $str($context) ?>;

    /**
     * The extension whose content is indexed.
     *
     * @var string
     */
    protected $extension = <?= $str($extension) ?>;

    /**
     * The com_finder sublayout used to render a result.
     *
     * @var string
     */
    protected $layout = <?= $str($layout) ?>;

    /**
     * The content type, registered automatically on instantiation.
     *
     * @var string
     */
    protected $type_title = <?= $str($typeTitle) ?>;

    /**
     * The table holding the indexable items.
     *
     * @var string
     */
    protected $table = <?= $str($table) ?>;

    /**
     * The column holding the published state.
     *
     * @var string
     */
    protected $state_field = <?= $str($stateField) ?>;
<?php if ($m->autoloadLanguage) : ?>

    /**
     * Load the language file on instantiation.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;
<?php endif; ?>

    /**
     * The parent Adapter already subscribes to onStartIndex, onBeforeIndex,
     * onBuildIndex and onFinderGarbageCollection. These are the events that
     * plg_content_finder re-dispatches when a single item changes.
     */
    public static function getSubscribedEvents(): array
    {
        return array_merge(parent::getSubscribedEvents(), [
<?php if ($hasCategories) : ?>
            'onFinderCategoryChangeState' => 'onFinderCategoryChangeState',
<?php endif; ?>
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
<?= $region('finder.setup') ?>

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
<?php if ($hasCategories) : ?>

        if ($event->getContext() === 'com_categories.category') {
            $this->checkCategoryAccess($event->getItem());
        }
<?php endif; ?>
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
<?php if ($hasCategories) : ?>

        if ($event->getContext() === 'com_categories.category'
            && !$event->getIsNew()
            && $this->old_cataccess != $row->access) {
            $this->categoryAccessChange($row);
        }
<?php endif; ?>
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
<?php if ($hasCategories) : ?>

    /**
     * A category changing state changes the effective state of every item in it.
     */
    public function onFinderCategoryChangeState(FinderEvent\AfterCategoryChangeStateEvent $event): void
    {
        if ($event->getExtension() === <?= $str($extension) ?>) {
            $this->categoryStateChange($event->getPks(), $event->getValue());
        }
    }
<?php endif; ?>

    /**
     * Turn one row from getListQuery() into an index entry.
     */
    protected function index(Result $item)
    {
        if (ComponentHelper::isEnabled($this->extension) === false) {
            return;
        }

        $item->setLanguage();
        $item->context = <?= $str($itemContext) ?>;

<?= $region('finder.index.before') ?>

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
<?= $region('finder.index.elements') ?>

<?php if ($hasCategories) : ?>
        $item->state = $this->translateState($item->state, $item->cat_state);
<?php else : ?>
        $item->state = $this->translateState($item->state);
<?php endif; ?>

<?php foreach ($taxonomies as $taxonomy) : ?>
<?php if ($taxonomy === 'Type') : ?>
        $item->addTaxonomy('Type', <?= $str($typeTitle) ?>);
<?php elseif ($taxonomy === 'Language') : ?>
        $item->addTaxonomy('Language', $item->language);
<?php else : ?>
        $item->addTaxonomy(<?= $str($taxonomy) ?>, $item-><?= strtolower($taxonomy) ?>);
<?php endif; ?>
<?php endforeach; ?>

<?= $region('finder.index.taxonomies') ?>

        Helper::getContentExtras($item);
        Helper::addCustomFields($item, <?= $str($itemContext) ?>);

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
<?php foreach ($columns as $column) : ?>
                        <?= $str($column['column']) ?>,
<?php endforeach; ?>
                    ],
                    [
<?php foreach ($columns as $column) : ?>
                        <?= ($column['alias'] ?? '') === '' ? 'null' : $str($column['alias']) ?>,
<?php endforeach; ?>
                    ]
                )
            )
            ->from($db->quoteName($this->table, 'a'));
<?php if ($hasCategories) : ?>

        $query->select($db->quoteName('c.published', 'cat_state'))
            ->select($db->quoteName('c.access', 'cat_access'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'));
<?php endif; ?>

<?= $region('finder.listquery') ?>

        return $query;
    }
<?php if (!$hasCategories) : ?>

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
<?php endif; ?>
}
