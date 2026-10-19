<?php

declare(strict_types=1);

namespace Gcego\Workflows\Scheduling;

use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Contracts\AssignmentResolver;
use Gcego\Workflows\Engine\CommandRunner;
use Gcego\Workflows\Engine\Effects;
use Gcego\Workflows\Engine\TransitionExecutor;
use Gcego\Workflows\Engine\TransitionResult;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\History\EventType;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Integration\WorkflowNotifier;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Tasks\TaskAssigner;
use Gcego\Workflows\Tasks\TaskFactory;
use Gcego\Workflows\Tasks\TaskStatus;
use Gcego\Workflows\Workflow;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Processes due deadlines (gcego:workflows:process-deadlines, on Laravel's
 * scheduler) as the "deadlines" system actor, each instance in its own
 * locked command, exactly once:
 *
 * - a state's Deadline: recorded; Then runs (it must name "deadlines"),
 *   Escalate moves the state's open tasks to the resolved people; a
 *   refused Then is recorded with its reason, not retried forever;
 * - an overdue task: marked and its assignee notified once; an approval
 *   task is escalated when its Approval names Escalate.
 *
 * Errors are reported and the item stays due for the next run.
 */
final readonly class DeadlineProcessor
{
    public function __construct(
        private CommandRunner $runner,
        private TransitionExecutor $executor,
        private HistoryRecorder $history,
        private TaskAssigner $assigner,
        private TaskFactory $tasks,
        private WorkflowNotifier $notifier,
        private WorkflowEngine $engine,
        private WorkflowsConfig $config,
    ) {}

    /**
     * @return array{deadlines: int, overdue: int, failed: int}
     */
    public function process(): array
    {
        $counts = ['deadlines' => 0, 'overdue' => 0, 'failed' => 0];
        $actor = SystemActor::named(SystemActor::Deadlines);

        $instances = WorkflowInstance::query()->where('status', InstanceStatus::Active->value)->whereNotNull('deadline_at')->where('deadline_at', '<=', now())->orderBy('deadline_at')->limit($this->config->batch)->get();

        foreach ($instances as $instance) {
            try {
                $this->runner->run('deadline', $instance, $actor, fn (WorkflowInstance $locked, Workflow $workflow, string $correlation, Effects $effects): TransitionResult => $this->deadline($locked, $workflow, $actor, $correlation, $effects));
                $counts['deadlines']++;
            } catch (Throwable $exception) {
                report($exception);
                $counts['failed']++;
            }
        }

        $tasks = WorkflowTask::query()->open()->whereNull('overdue_at')->whereNotNull('due_at')->where('due_at', '<=', now())->orderBy('due_at')->limit($this->config->batch)->with('instance')->get();

        foreach ($tasks as $task) {
            try {
                $this->runner->run('overdue', $task->instance, $actor, fn (WorkflowInstance $locked, Workflow $workflow, string $correlation, Effects $effects): TransitionResult => $this->overdue($task, $locked, $workflow, $actor, $correlation, $effects));
                $counts['overdue']++;
            } catch (Throwable $exception) {
                report($exception);
                $counts['failed']++;
            }
        }

        return $counts;
    }

    private function deadline(WorkflowInstance $instance, Workflow $workflow, SystemActor $actor, string $correlation, Effects $effects): TransitionResult
    {
        $unchanged = new TransitionResult($instance->ulid, 'deadline', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);

        if ($instance->status !== InstanceStatus::Active || $instance->deadline_at === null || $instance->deadline_at->isFuture()) {
            return $unchanged;
        }

        $deadline = $workflow->state($instance->state)?->deadline();
        $instance->forceFill(['deadline_at' => null]);
        $then = $deadline?->then();
        $refused = null;

        if ($then !== null) {
            try {
                $this->executor->check($instance, $workflow, $then, $actor);
            } catch (InvalidTransitionException $exception) {
                $refused = $exception->reason;
            } catch (WorkflowException $exception) {
                $refused = class_basename($exception);
            }
        }

        $this->history->record($instance, EventType::DeadlineReached, $actor, $correlation, $effects, from: $instance->state, metadata: ['then' => $then, 'refused' => $refused, 'escalated' => $deadline?->escalate() !== null]);

        if ($deadline?->escalate() !== null) {
            foreach ($instance->tasks()->open()->get() as $task) {
                $task->setRelation('instance', $instance);
                $this->escalate($task, $deadline->escalate(), $instance, $workflow, $actor, $correlation, $effects);
            }
        }

        if ($then !== null && $refused === null) {
            return $this->executor->apply($instance, $workflow, $then, $actor, null, [], $correlation, $effects);
        }

        return $unchanged;
    }

    private function overdue(WorkflowTask $task, WorkflowInstance $instance, Workflow $workflow, SystemActor $actor, string $correlation, Effects $effects): TransitionResult
    {
        $task = WorkflowTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
        $task->setRelation('instance', $instance);
        $result = new TransitionResult($instance->ulid, 'overdue', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);

        if (! $task->isOpen() || $task->overdue_at !== null || $instance->status !== InstanceStatus::Active) {
            return $result;
        }

        $task->forceFill(['overdue_at' => now()])->save();
        $this->history->record($instance, EventType::TaskOverdue, $actor, $correlation, $effects, task: $task, metadata: ['task' => $task->ulid]);
        $this->notifier->overdue($task, $effects);

        $resolver = $task->isApproval() ? $workflow->approval($task->name)?->escalate() : null;

        if ($resolver !== null) {
            $this->escalate($task, $resolver, $instance, $workflow, $actor, $correlation, $effects);
        }

        return $result;
    }

    /**
     * @param  class-string<AssignmentResolver>  $resolver
     */
    private function escalate(WorkflowTask $task, string $resolver, WorkflowInstance $instance, Workflow $workflow, SystemActor $actor, string $correlation, Effects $effects): void
    {
        $selfApproval = $task->isApproval() && ($workflow->approval($task->name)?->allowsSelfApproval($this->config->selfApproval) ?? false);
        $targets = $this->assigner->resolve($resolver, new WorkflowContext($instance, $workflow, $actor), $this->engine->requirementsOf($task, $workflow), $task->isApproval() && ! $selfApproval);
        $targets = array_values(array_filter($targets, fn (Model $target): bool => ! $task->isAssignedTo($target) && ! $instance->tasks()->open()->where('name', $task->name)->where('stage_index', $task->stage_index)->where('round', $task->round)
            ->where('assignee_type', $target->getMorphClass())->where('assignee_id', Keys::string($target->getKey()))->exists()));

        if ($targets === []) {
            $this->history->record($instance, EventType::TaskEscalated, $actor, $correlation, $effects, task: $task, metadata: ['task' => $task->ulid, 'targets' => 0]);

            return;
        }

        $task->forceFill(['status' => TaskStatus::Escalated, 'escalated_at' => now(), 'completed_at' => now()])->save();

        foreach ($targets as $target) {
            $this->tasks->create($instance, $target, ['kind' => $task->kind, 'name' => $task->name, 'stage' => $task->stage, 'stage_index' => $task->stage_index, 'round' => $task->round, 'priority' => 'high', 'delegations' => $task->delegations], $actor, $correlation, $effects);
        }

        $this->history->record($instance, EventType::TaskEscalated, $actor, $correlation, $effects, task: $task, metadata: ['task' => $task->ulid, 'targets' => count($targets)]);
    }
}
