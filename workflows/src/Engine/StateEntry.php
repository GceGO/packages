<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Approvals\ApprovalCoordinator;
use Gcego\Workflows\Automation\AutomationDispatcher;
use Gcego\Workflows\History\EventType;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Integration\WorkflowNotifier;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Support\Duration;
use Gcego\Workflows\Tasks\TaskAssigner;
use Gcego\Workflows\Tasks\TaskFactory;
use Gcego\Workflows\Tasks\TaskStatus;
use Gcego\Workflows\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Entering a state (inside the command transaction): open work of the
 * previous state is cancelled, the state's tasks and approvals open, its
 * deadline is set, a terminal state completes the instance, and its
 * automation is scheduled for after the commit.
 */
final readonly class StateEntry
{
    public function __construct(
        private ApprovalCoordinator $approvals,
        private TaskAssigner $assigner,
        private TaskFactory $tasks,
        private HistoryRecorder $history,
        private AutomationDispatcher $automation,
        private WorkflowNotifier $notifier,
    ) {}

    public function enter(WorkflowInstance $instance, Workflow $workflow, string $state, int $sequence, Model|SystemActor|null $actor, string $correlation, Effects $effects): void
    {
        $definition = $workflow->state($state) ?? throw new \LogicException('Unknown state.');

        $instance->tasks()->open()->update(['status' => TaskStatus::Cancelled->value, 'completed_at' => now(), 'updated_at' => now()]);

        $deadline = $definition->deadline();
        $instance->forceFill([
            'state' => $state,
            'state_entered_at' => now(),
            'state_sequence' => $sequence,
            'deadline_at' => $deadline === null || $definition->isTerminal() ? null : Duration::after($deadline->after()),
        ]);

        if ($definition->isTerminal()) {
            $instance->forceFill(['status' => InstanceStatus::Completed, 'running' => null, 'completed_at' => now()]);
            $this->history->record($instance, EventType::Completed, $actor, $correlation, $effects, to: $state);
            $this->notifier->finished($instance, 'completed', $effects);

            return;
        }

        if ($instance->status !== InstanceStatus::Active) {
            $instance->forceFill(['status' => InstanceStatus::Active, 'running' => 1, 'completed_at' => null, 'cancelled_at' => null]);
        }

        foreach ($definition->tasks() as $task) {
            $assignees = $task->assign() === null ? [] : $this->assigner->resolve($task->assign(), new WorkflowContext($instance, $workflow, $actor), $task->requirements());
            $attributes = ['kind' => WorkflowTask::Task, 'name' => $task->nodeName(), 'priority' => $task->priority(), 'due' => $task->due()];

            foreach ($assignees === [] ? [null] : $assignees as $assignee) {
                $this->tasks->create($instance, $assignee, $attributes, $actor, $correlation, $effects);
            }
        }

        foreach ($workflow->approvals() as $approval) {
            if ($approval->state() === $state) {
                $this->approvals->open($approval, $instance, $workflow, $actor, $correlation, $effects);
            }
        }

        foreach ($definition->automations() as $automation) {
            $this->automation->schedule($instance, $automation, 'state:'.$state, $correlation, $effects);
        }
    }
}
