<?php
/**
 * @package    {{ projectName }}
 * @subpackage {{ pluginType }} - {{ pluginName }}
 * @version    {{ version }}
 *
 * @copyright  {{ copyright }}
 * @license    {{ license }}
 */

namespace administrator\components\com_pluggen\generator_templates\Joomla6\plugin\type\task;

namespace {
    {
        company_namespace }
}
\Component\{
    {
        pluginType }
}\{
    {
        pluginName }
}\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. Session data purge task.
 * {@see ExecuteTaskEvent}.
 *
 * @since 5.0.0
 */
final class
{
{
pluginName
}} extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    {
        {
            traits }
    }{
    {
        properties }
}
    /**
     * @var string[]
     * @since 5.0.0
     */
    private
    const TASKS_MAP = [
        '{{taskId}}' => [
            'langConstPrefix' => 'PLG_{{ pluginType|upper }}_{{ pluginName|upper }}',
            'method' => '{{ pluginName }}Handler',
            'form' => 'sessionGCForm',
        ],
    ];

    /**
     * @var boolean
     * @since 5.0.0
     */
    protected
    $autoloadLanguage = true;
    {
        {
            constructor }
    }
    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 5.0.0
     */
    public
    static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @param ExecuteTaskEvent $event The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @throws \Exception
     * @since  5.0.0
     */
    private
    function {
        {
            pluginName }
    }Handler(ExecuteTaskEvent $event): int
    {
        $enableGC = (int)$event->getArgument('params')->enable_session_gc ?? 1;
        $app = $this->getApplication();

        if ($enableGC) {
            $app->getSession()->gc();
        }

        $enableMetadata = (int)$event->getArgument('params')->enable_session_metadata_gc ?? 1;

        if ($enableMetadata) {
            $this->metadataManager->deletePriorTo(time() - $app->getSession()->getExpire());
        }

        $this->logTask('SessionGC end');

        return Status::OK;
    }
}
