<?php
/**
 * Template for a workflow plugin.
 *
 * Note the blank lines after every $region() call. PHP swallows the newline
 * that follows a closing tag, so a single newline here leaves none in the
 * output and the next line runs into the region's closing marker.
 *
 * @var \Yepr\Component\Pluggen\Administrator\Generator\Model\PluginModel $m
 * @var string[] $contexts  the contexts this plugin supports
 * @var array    $actions   each with name, type, label, key
 * @var callable $str       PHP string literal
 * @var callable $arr       PHP array literal
 * @var callable $id        validated PHP identifier
 * @var callable $region    protected region, pre-filled from the model slot
 * @var callable $slot      raw code stored for a slot
 */

$functionality = (string) $m->config('supportFunctionality', '');
$handleBefore  = (bool) $m->config('handleBeforeTransition', false);
$members       = $slot('workflow.class.members');

// A trait is declared together with the interface it implements, so the class
// says what it can be given rather than only how it stores it.
$traits     = ['WorkflowPluginTrait'];
$interfaces = ['SubscriberInterface'];
$uses       = [
    'Joomla\\CMS\\Event\\Model',
    'Joomla\\CMS\\Event\\Workflow\\WorkflowTransitionEvent',
    'Joomla\\CMS\\Plugin\\CMSPlugin',
    'Joomla\\CMS\\Workflow\\WorkflowPluginTrait',
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

echo "<?php\n";
?>

/**
 * @package     <?= str_replace('\\', '.', $m->namespace) ?>

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
 * Workflow plugin: actions executed at a transition.
 */
final class <?= $id($m->className) ?> extends CMSPlugin implements <?= implode(', ', $interfaces) ?>

{
<?php foreach ($traits as $trait) : ?>
    use <?= $trait ?>;
<?php endforeach; ?>

    /**
     * The contexts this plugin offers its actions for.
     *
     * A context is written as component.item, the same string the transition
     * event carries in its "extension" argument.
     *
     * @var string[]
     */
    private const CONTEXTS = <?= $arr($contexts, 1) ?>;
<?php if ($m->autoloadLanguage) : ?>

    /**
     * Load the language file on instantiation. Without this the fields added to
     * the transition form show their language keys instead of their labels.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;
<?php endif; ?>
<?php if ($functionality !== '') : ?>

    /**
     * The functionality a component must support for this plugin to act on it.
     *
     * @var string
     */
    protected $supportFunctionality = <?= $str($functionality) ?>;
<?php endif; ?>

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm'       => 'onContentPrepareForm',
<?php if ($handleBefore) : ?>
            'onWorkflowBeforeTransition' => 'onWorkflowBeforeTransition',
<?php endif; ?>
            'onWorkflowAfterTransition'  => 'onWorkflowAfterTransition',
        ];
    }
<?php if (trim($members) !== '') : ?>

<?= $region('workflow.class.members', 1) ?>


<?php endif; ?>

    /**
     * Add this plugin's action fields to the transition form.
     *
     * enhanceWorkflowTransitionForm() from WorkflowPluginTrait does the work: it
     * reads forms/action.xml and merges it in. Only the transition form is
     * touched, because this same event fires for every form in Joomla.
     *
     * @param   Model\PrepareFormEvent  $event  The event.
     *
     * @return  void
     */
    public function onContentPrepareForm(Model\PrepareFormEvent $event): void
    {
        $form = $event->getForm();

        if ($form->getName() !== 'com_workflow.transition') {
            return;
        }

        $this->enhanceWorkflowTransitionForm($form, $event->getData());
    }
<?php if ($handleBefore) : ?>

    /**
     * Runs before the transition is carried out.
     *
     * The place to check whether the transition may happen at all, and to keep
     * hold of anything it is about to overwrite. Call
     * $event->setStopTransition() to prevent the transition.
     *
     * @param   WorkflowTransitionEvent  $event  The event.
     *
     * @return  void
     */
    public function onWorkflowBeforeTransition(WorkflowTransitionEvent $event): void
    {
        $context = $event->getArgument('extension');

        if (!$this->isSupported($context)) {
            return;
        }

        $transition = $event->getArgument('transition');
        $pks        = $event->getArgument('pks');

<?= $region('workflow.before', 2) ?>

    }
<?php endif; ?>

    /**
     * Runs after the transition: the actions themselves.
     *
     * The values entered in the transition form arrive in $transition->options,
     * a Registry keyed by the field names from forms/action.xml.
     *
     * Note that $pks is an array: a transition can be applied to a batch of
     * items at once, so anything per-item belongs inside a loop over it.
     *
     * @param   WorkflowTransitionEvent  $event  The event.
     *
     * @return  void
     */
    public function onWorkflowAfterTransition(WorkflowTransitionEvent $event): void
    {
        $context = $event->getArgument('extension');

        if (!$this->isSupported($context)) {
            return;
        }

        $extensionName = $event->getArgument('extensionName');
        $transition    = $event->getArgument('transition');
        $pks           = $event->getArgument('pks');

<?php foreach ($actions as $action) : ?>
        $<?= $id((string) $action['name']) ?> = $transition->options->get(<?= $str($action['name']) ?>);
<?php endforeach; ?>

<?= $region('workflow.after', 2) ?>

    }

    /**
     * Whether this plugin acts on the given context.
     *
     * WorkflowPluginTrait returns false here, so a workflow plugin that does not
     * override this method is never asked to do anything. That is the first
     * thing to check when a workflow plugin appears to do nothing at all.
     *
     * @param   string  $context  The context to check.
     *
     * @return  boolean  True when this plugin handles the context.
     */
    protected function isSupported($context)
    {
        if (!\in_array($context, self::CONTEXTS, true)) {
            return false;
        }

        // Honours the allowed and forbidden lists in the plugin parameters.
        if (!$this->checkAllowedAndForbiddenlist($context)) {
            return false;
        }
<?php if ($functionality !== '') : ?>

        if (!$this->checkExtensionSupport($context, $this->supportFunctionality)) {
            return false;
        }
<?php endif; ?>

<?= $region('workflow.issupported', 2) ?>


        return true;
    }
}
