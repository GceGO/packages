<?php

declare(strict_types=1);

namespace Gcego\Workflows\Integration;

use Gcego\Notifications\Notification;
use Gcego\Workflows\Engine\Effects;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * gcego/notifications messages (category "workflows", database channel,
 * in the instance's tenant), sent only after the command commits and
 * idempotent per task or instance event. They name the workflow and the
 * task, never subject data or comments. Delivery failures are reported,
 * never thrown into the committed command.
 */
final readonly class WorkflowNotifier
{
    public const string Category = 'workflows';

    public function __construct(private WorkflowsConfig $config, private WorkflowDefinitions $definitions) {}

    public function assigned(WorkflowTask $task, Model $assignee, Effects $effects): void
    {
        $effects->after(fn () => $this->send($task->instance, $assignee, $task->isApproval() ? 'approval' : 'assigned', 'task.'.$task->ulid.'.assigned', ['task' => $this->taskLabel($task)]));
    }

    public function overdue(WorkflowTask $task, Effects $effects): void
    {
        $assignee = $task->pool ? null : $task->assignee;

        if ($assignee instanceof Model) {
            $effects->after(fn () => $this->send($task->instance, $assignee, 'overdue', 'task.'.$task->ulid.'.overdue', ['task' => $this->taskLabel($task)]));
        }
    }

    public function finished(WorkflowInstance $instance, string $outcome, Effects $effects): void
    {
        $initiator = $instance->initiator;

        if ($initiator instanceof Model) {
            $effects->after(fn () => $this->send($instance, $initiator, $outcome, 'instance.'.$instance->ulid.'.'.$outcome.'.'.$instance->state_sequence, ['state' => $this->stateLabel($instance)]));
        }
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function send(WorkflowInstance $instance, Model $recipient, string $message, string $key, array $parameters): void
    {
        if (! $this->config->notifications || ! class_exists(Notification::class) || ! method_exists($recipient, 'routeNotificationFor')) {
            return;
        }

        try {
            $builder = Notification::make()
                ->title('gcego-workflows::messages.notifications.'.$message.'.title')
                ->body('gcego-workflows::messages.notifications.'.$message.'.body')
                ->translate(['workflow' => $this->title($instance), ...$parameters])
                ->category(self::Category)
                ->database()
                ->idempotencyKey('workflows.'.$key);
            $builder = match ($message) {
                'overdue', 'rejected', 'cancelled' => $builder->warning(),
                'completed' => $builder->success(),
                default => $builder->info(),
            };
            $builder = $instance->tenant === null ? $builder->global() : $builder->tenant($instance->tenant);
            $builder->sendTo($recipient);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function title(WorkflowInstance $instance): string
    {
        try {
            return $this->definitions->resolve($instance->workflow)->title();
        } catch (Throwable) {
            return $instance->workflow;
        }
    }

    private function taskLabel(WorkflowTask $task): string
    {
        try {
            $workflow = $this->definitions->resolve($task->instance->workflow);

            if ($task->isApproval()) {
                return $workflow->approval($task->name)?->label() ?? $task->name;
            }

            foreach ($workflow->state($task->state)?->tasks() ?? [] as $definition) {
                if ($definition->nodeName() === $task->name) {
                    return $definition->label();
                }
            }
        } catch (Throwable) {
        }

        return $task->name;
    }

    private function stateLabel(WorkflowInstance $instance): string
    {
        try {
            return $this->definitions->resolve($instance->workflow)->state($instance->state)?->label() ?? $instance->state;
        } catch (Throwable) {
            return $instance->state;
        }
    }
}
