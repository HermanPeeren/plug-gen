<?php
/**
 * Template for a scheduled task plugin.
 *
 * @var \Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel $m
 * @var array    $routines  each with id, method, label, langPrefix, formName, params, code
 * @var callable $str       PHP string literal
 * @var callable $id        validated PHP identifier
 * @var callable $region    protected region, pre-filled
 * @var callable $slot      raw code stored for a plugin-wide slot
 */

// Setter injection uses a trait, and the trait's interface is declared beside
// it - a class should say what it can be given, not only how it stores it.
$traits     = ['TaskPluginTrait'];
$interfaces = ['SubscriberInterface'];
$uses       = [
    'Joomla\\CMS\\Plugin\\CMSPlugin',
    'Joomla\\Component\\Scheduler\\Administrator\\Event\\ExecuteTaskEvent',
    'Joomla\\Component\\Scheduler\\Administrator\\Task\\Status',
    'Joomla\\Component\\Scheduler\\Administrator\\Traits\\TaskPluginTrait',
    'Joomla\\Event\\SubscriberInterface',
];

if ($m->service('database')) {
    $traits[]     = 'DatabaseAwareTrait';
    $interfaces[] = 'DatabaseAwareInterface';
    $uses[]       = 'Joomla\\Database\\DatabaseAwareInterface';
    $uses[]       = 'Joomla\\Database\\DatabaseAwareTrait';
}

if ($m->service('mailerFactory')) {
    $traits[]     = 'MailerFactoryAwareTrait';
    $interfaces[] = 'MailerFactoryAwareInterface';
    $uses[]       = 'Joomla\\CMS\\Mail\\MailerFactoryAwareInterface';
    $uses[]       = 'Joomla\\CMS\\Mail\\MailerFactoryAwareTrait';
}

if ($m->service('userFactory')) {
    $traits[]     = 'UserFactoryAwareTrait';
    $interfaces[] = 'UserFactoryAwareInterface';
    $uses[]       = 'Joomla\\CMS\\User\\UserFactoryAwareInterface';
    $uses[]       = 'Joomla\\CMS\\User\\UserFactoryAwareTrait';
}

sort($uses, SORT_STRING);

$members     = $slot('task.class.members');
$constructor = $slot('task.constructor');

echo "<?php\n";
?>

/**
 * @package     <?= str_replace('\\', '.', $m->rootNamespace()) ?>

 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace <?= $m->extensionNamespace() ?>;

<?php foreach ($uses as $use) : ?>
use <?= $use ?>;
<?php endforeach; ?>

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Task plugin with <?= \count($routines) === 1 ? 'a routine' : \count($routines) . ' routines' ?> for the Joomla Task Scheduler.
 */
final class <?= $id($m->className()) ?> extends CMSPlugin implements <?= implode(', ', $interfaces) ?>

{
<?php foreach ($traits as $trait) : ?>
    use <?= $trait ?>;
<?php endforeach; ?>

    /**
     * The routines this plugin offers the scheduler.
     *
     * Each entry tells TaskPluginTrait where to find everything: the language
     * constant the scheduler shows the routine under, the method that does the
     * work, and the form holding the routine's own parameters.
     *
     * @var array<string, array<string, string>>
     */
    private const TASKS_MAP = [
<?php foreach ($routines as $routine) : ?>
        <?= $str($routine['id']) ?> => [
            'langConstPrefix' => <?= $str($routine['langPrefix']) ?>,
            'method'          => <?= $str($routine['method']) ?>,
<?php if ((array) ($routine['params'] ?? []) !== []) : ?>
            'form'            => <?= $str($routine['formName']) ?>,
<?php endif; ?>
        ],
<?php endforeach; ?>
    ];
<?php if ($m->autoloadLanguage) : ?>

    /**
     * Load the language file on instantiation.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;
<?php endif; ?>

    /**
     * The three events a task plugin handles, each pointing at a method of
     * TaskPluginTrait. The trait reads TASKS_MAP to do the rest.
     *
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }
<?php if (trim($members) !== '') : ?>

<?= $region('task.class.members', $members, 1) ?>
<?php endif; ?>
<?php if (trim($constructor) !== '') : ?>

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

<?= $region('task.constructor', $constructor, 2) ?>
    }
<?php endif; ?>
<?php foreach ($routines as $routine) : ?>


    /**
     * <?= $routine['label'] ?? $routine['id'] ?>

     *
     * Called by standardRoutineHandler() from TaskPluginTrait, which turns the
     * status returned here into the snapshot the scheduler stores. Private on
     * purpose: the scheduler never calls this method directly.
     *
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event.
     *
     * @return  integer  A Status constant: OK when the work is done.
     */
    private function <?= $id($routine['method']) ?>(ExecuteTaskEvent $event): int
    {
<?php if ((array) ($routine['params'] ?? []) !== []) : ?>
        // Parameters come from forms/<?= $routine['formName'] ?>.xml.
        $params = $event->getArgument('params');

<?php endif; ?>
<?= $region('task.routine.' . $routine['method'], (string) ($routine['code'] ?? ''), 2) ?>


        return Status::OK;
    }
<?php endforeach; ?>
}
