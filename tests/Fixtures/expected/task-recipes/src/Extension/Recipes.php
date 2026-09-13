<?php

/**
 * @package     Acme.Plugin.Task.Recipes
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Acme\Plugin\Task\Recipes\Extension;

use Joomla\CMS\Mail\MailerFactoryAwareInterface;
use Joomla\CMS\Mail\MailerFactoryAwareTrait;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Task plugin with 2 routines for the Joomla Task Scheduler.
 */
final class Recipes extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface, MailerFactoryAwareInterface
{
    use TaskPluginTrait;
    use DatabaseAwareTrait;
    use MailerFactoryAwareTrait;

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
        'recipes.cleanup' => [
            'langConstPrefix' => 'PLG_TASK_RECIPES_CLEANUP',
            'method'          => 'cleanUp',
            'form'            => 'cleanUp',
        ],
        'recipes.report' => [
            'langConstPrefix' => 'PLG_TASK_RECIPES_REPORT',
            'method'          => 'mailReport',
            'form'            => 'mailReport',
        ],
    ];

    /**
     * Load the language file on instantiation.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

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

    // <pluggen id="task.class.members">
    /**
     * How many rows a single run may touch.
     *
     * @var integer
     */
    private const HARD_LIMIT = 500;
    // </pluggen>

    /**
     * Remove unpublished recipes
     *
     * Called by standardRoutineHandler() from TaskPluginTrait, which turns the
     * status returned here into the snapshot the scheduler stores. Private on
     * purpose: the scheduler never calls this method directly.
     *
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event.
     *
     * @return  integer  A Status constant: OK when the work is done.
     */
    private function cleanUp(ExecuteTaskEvent $event): int
    {
        // Parameters come from forms/cleanUp.xml.
        $params = $event->getArgument('params');

        // <pluggen id="task.routine.cleanUp">
        $db     = $this->getDatabase();
        $days   = max(1, (int) $params->max_age_days);
        $limit  = max(1, (int) $params->max_items);
        $cutoff = (new \DateTimeImmutable('-' . $days . ' days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->createQuery()
            ->delete($db->quoteName('#__recipes'))
            ->where($db->quoteName('published') . ' = 0')
            ->where($db->quoteName('modified') . ' < :cutoff')
            ->bind(':cutoff', $cutoff)
            ->setLimit($limit);

        $db->setQuery($query)->execute();

        $this->logTask(\sprintf('Removed %d recipes.', $db->getAffectedRows()));
        // </pluggen>

        return Status::OK;
    }


    /**
     * Mail a recipe report
     *
     * Called by standardRoutineHandler() from TaskPluginTrait, which turns the
     * status returned here into the snapshot the scheduler stores. Private on
     * purpose: the scheduler never calls this method directly.
     *
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event.
     *
     * @return  integer  A Status constant: OK when the work is done.
     */
    private function mailReport(ExecuteTaskEvent $event): int
    {
        // Parameters come from forms/mailReport.xml.
        $params = $event->getArgument('params');

        // <pluggen id="task.routine.mailReport">
        $mailer = $this->getMailerFactory()->createMailer();

        $mailer->addRecipient($params->recipient);
        $mailer->setSubject('Recipe report');
        $mailer->setBody('Nothing to report yet.');
        $mailer->Send();
        // </pluggen>

        return Status::OK;
    }
}
