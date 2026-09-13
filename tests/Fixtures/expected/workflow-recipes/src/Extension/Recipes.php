<?php

/**
 * @package     Acme.Plugin.Workflow.Recipes
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Acme\Plugin\Workflow\Recipes\Extension;

use Joomla\CMS\Event\Model;
use Joomla\CMS\Event\Workflow\WorkflowTransitionEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Workflow\WorkflowPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Workflow plugin: actions executed at a transition.
 */
final class Recipes extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use WorkflowPluginTrait;
    use DatabaseAwareTrait;

    /**
     * The contexts this plugin offers its actions for.
     *
     * A context is written as component.item, the same string the transition
     * event carries in its "extension" argument.
     *
     * @var string[]
     */
    private const CONTEXTS = [
        'com_recipes.recipe',
    ];

    /**
     * Load the language file on instantiation. Without this the fields added to
     * the transition form show their language keys instead of their labels.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * The functionality a component must support for this plugin to act on it.
     *
     * @var string
     */
    protected $supportFunctionality = 'core.state';

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm'       => 'onContentPrepareForm',
            'onWorkflowBeforeTransition' => 'onWorkflowBeforeTransition',
            'onWorkflowAfterTransition'  => 'onWorkflowAfterTransition',
        ];
    }

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

        // <pluggen id="workflow.before">
        // A recipe that is checked out by somebody else must not be moved.
        foreach ($pks as $pk) {
            if ($this->isCheckedOut((int) $pk)) {
                $event->setStopTransition();

                return;
            }
        }
        // </pluggen>
    }

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

        $target_category = $transition->options->get('target_category');
        $notify_author = $transition->options->get('notify_author');

        // <pluggen id="workflow.after">
        if (is_numeric($target_category)) {
            $this->moveToCategory($pks, (int) $target_category);
        }

        if ($notify_author) {
            $this->notifyAuthors($pks);
        }
        // </pluggen>
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

        if (!$this->checkExtensionSupport($context, $this->supportFunctionality)) {
            return false;
        }

        // <pluggen id="workflow.issupported">
        // </pluggen>

        return true;
    }
}
