<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

use Gcego\Security\Contracts\Requirement;
use Gcego\Workflows\Access\WorkflowAuthorizer;
use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Approvals\ApprovalCoordinator;
use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\Exceptions\WorkflowConflictException;
use Gcego\Workflows\Exceptions\WorkflowDefinitionException;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\History\EventType;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Integration\WorkflowNotifier;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Tasks\TaskAssigner;
use Gcego\Workflows\Tasks\TaskFactory;
use Gcego\Workflows\Tasks\TaskStatus;
use Gcego\Workflows\Workflow;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The commands of gcego/workflows. Actors are always given by server code
 * (the authenticated user, or a SystemActor), never read from requests;
 * states and transitions are always the server's. Every command is
 * authorized, tenant-scoped, locked, versioned and recorded.
 *
 *     $instance = $engine->start('institution-registration', $institution, $user);
 *     $engine->transition($instance, 'submit', $user, expectedVersion: $version);
 *     $engine->decide($task, Decision::Approve, $reviewer, 'Looks complete.');
 */
final readonly class WorkflowEngine
{
    public function __construct(
        private WorkflowDefinitions $definitions,
        private CommandRunner $runner,
        private TransitionExecutor $executor,
        private StateEntry $entry,
        private ApprovalCoordinator $approvals,
        private WorkflowAuthorizer $authorizer,
        private TaskAssigner $assigner,
        private TaskFactory $tasks,
        private HistoryRecorder $history,
        private WorkflowsConfig $config,
        private WorkflowNotifier $notifier,
    ) {}

    /**
     * Starts a workflow for a subject in the current tenant (system actors may name another tenant).
     */
    public function start(Workflow|string $workflow, Model $subject, Model|SystemActor $actor, ?string $idempotencyKey = null, ?string $tenant = null): WorkflowInstance
    {
        $workflow = is_string($workflow) ? $this->definitions->resolve($workflow) : $this->definitions->resolve($workflow->workflowName());
        $tenant = $actor instanceof SystemActor && $tenant !== null ? $tenant : $this->runner->currentTenant();

        if ($subject->getKey() === null || ($workflow->subjectClass() !== null && ! $subject instanceof ($workflow->subjectClass()))) {
            throw WorkflowDefinitionException::invalid(sprintf('Workflow "%s"', $workflow->workflowName()), 'it cannot run for this subject.');
        }

        if ($actor instanceof Model && ! $this->authorizer->canStart($workflow, $subject, $actor, $tenant)) {
            $this->runner->refuse('start', null, $actor, WorkflowAuthorizationException::for('start this workflow'));
        }

        if (! $workflow->allowsConcurrentInstances() && $idempotencyKey === null && $this->running($workflow->workflowName(), $subject) !== null) {
            $this->runner->refuse('start', null, $actor, new WorkflowConflictException((string) __('gcego-workflows::messages.errors.running')));
        }

        $instance = new WorkflowInstance;
        $instance->forceFill([
            'ulid' => (string) Str::ulid(),
            'workflow' => $workflow->workflowName(),
            'version' => $workflow->version(),
            'structure' => $workflow->structureHash(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => Keys::string($subject->getKey()),
            'tenant' => $tenant,
            'state' => $workflow->initialState(),
            'status' => InstanceStatus::Active,
            'initiator_type' => $actor instanceof Model ? $actor->getMorphClass() : null,
            'initiator_id' => $actor instanceof Model ? Keys::string($actor->getKey()) : null,
            'lock_version' => 1,
            'running' => $workflow->allowsConcurrentInstances() ? null : 1,
            'started_at' => now(),
        ]);
        $instance->setRelation('subject', $subject);

        return $this->runner->create($instance, $actor, function (WorkflowInstance $instance, Effects $effects) use ($workflow, $actor): void {
            $correlation = (string) Str::ulid();
            $event = $this->history->record($instance, EventType::Started, $actor, $correlation, $effects, to: $instance->state, metadata: ['version' => $workflow->version()]);
            $this->entry->enter($instance, $workflow, $instance->state, $event->sequence, $actor, $correlation, $effects);
        }, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $input  Trusted, validated values for guards and handlers (never states or actors).
     */
    public function transition(WorkflowInstance $instance, string $transition, Model|SystemActor $actor, ?string $comment = null, ?int $expectedVersion = null, ?string $idempotencyKey = null, array $input = []): TransitionResult
    {
        return $this->runner->run('transition:'.$transition, $instance, $actor, function (WorkflowInstance $locked, Workflow $workflow, string $correlation, Effects $effects) use ($transition, $actor, $comment, $input): TransitionResult {
            return $this->executor->apply($locked, $workflow, $transition, $actor, $comment, $input, $correlation, $effects);
        }, $expectedVersion, $idempotencyKey);
    }

    /**
     * Transitions this person may run now, and those they may run once a guard or approval allows it.
     *
     * @return list<AvailableTransition>
     */
    public function available(WorkflowInstance $instance, Model $user): array
    {
        try {
            $this->runner->scope($instance, $user);
            $workflow = $this->definitions->forInstance($instance);
        } catch (WorkflowException) {
            return [];
        }

        $available = [];

        foreach ($workflow->transitionsFrom($instance->state) as $transition) {
            try {
                $this->executor->check($instance, $workflow, $transition->nodeName(), $user, guards: false, comments: false);
            } catch (WorkflowAuthorizationException) {
                continue;
            } catch (InvalidTransitionException $exception) {
                if (in_array($exception->reason, ['state', 'finished', 'suspended'], true)) {
                    continue;
                }

                $available[] = new AvailableTransition($transition, $exception->getMessage());

                continue;
            }

            try {
                $this->executor->check($instance, $workflow, $transition->nodeName(), $user, comments: false);
                $available[] = new AvailableTransition($transition);
            } catch (WorkflowException $exception) {
                $available[] = new AvailableTransition($transition, $exception->getMessage());
            }
        }

        return $available;
    }

    public function decide(WorkflowTask $task, Decision|string $decision, Model $actor, ?string $comment = null, ?int $expectedVersion = null, ?string $idempotencyKey = null): TransitionResult
    {
        $decision = $decision instanceof Decision ? $decision : (Decision::tryFrom($decision) ?? throw InvalidTransitionException::because('decision'));

        return $this->runner->run('decide', $task->instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects) use ($task, $decision, $actor, $comment): TransitionResult {
            $task = $this->lockedTask($task, $instance);
            $this->active($instance);
            $from = $instance->state;
            $outcome = $this->approvals->decide($task, $decision, $actor, $comment, $instance, $workflow, $correlation, $effects);

            if ($outcome !== null) {
                return $this->executor->apply($instance, $workflow, $outcome, SystemActor::named(SystemActor::Approvals), null, [], $correlation, $effects);
            }

            return new TransitionResult($instance->ulid, 'decide', $from, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
        }, $expectedVersion, $idempotencyKey);
    }

    /**
     * Takes a pool task (or starts an assigned one).
     */
    public function claim(WorkflowTask $task, Model $actor): TransitionResult
    {
        return $this->runner->run('claim', $task->instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects) use ($task, $actor): TransitionResult {
            $task = $this->lockedTask($task, $instance);
            $this->active($instance);
            $this->mayWorkOn($task, $instance, $workflow, $actor);

            if ($task->isApproval()) {
                throw InvalidTransitionException::because('claim');
            }

            $task->forceFill(['assignee_type' => $actor->getMorphClass(), 'assignee_id' => Keys::string($actor->getKey()), 'pool' => false, 'status' => TaskStatus::InProgress])->save();
            $this->history->record($instance, EventType::TaskClaimed, $actor, $correlation, $effects, task: $task, metadata: ['task' => $task->ulid]);

            return new TransitionResult($instance->ulid, 'claim', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
        });
    }

    /**
     * Completes a task; its Completes transition then runs as the "tasks" system actor.
     */
    public function completeTask(WorkflowTask $task, Model $actor, ?string $comment = null, ?int $expectedVersion = null, ?string $idempotencyKey = null): TransitionResult
    {
        return $this->runner->run('complete', $task->instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects) use ($task, $actor, $comment): TransitionResult {
            $task = $this->lockedTask($task, $instance);
            $this->active($instance);

            if ($task->isApproval()) {
                throw InvalidTransitionException::because('task_closed');
            }

            $this->mayWorkOn($task, $instance, $workflow, $actor);
            $task->forceFill(['assignee_type' => $actor->getMorphClass(), 'assignee_id' => Keys::string($actor->getKey()), 'pool' => false, 'status' => TaskStatus::Completed, 'completed_at' => now()])->save();
            $instance->tasks()->open()->where('kind', WorkflowTask::Task)->where('name', $task->name)->update(['status' => TaskStatus::Cancelled->value, 'completed_at' => now(), 'updated_at' => now()]);
            $this->history->record($instance, EventType::TaskCompleted, $actor, $correlation, $effects, task: $task, metadata: ['task' => $task->ulid, 'name' => $task->name, 'comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 2000)]);

            $definition = array_values(array_filter($workflow->state($instance->state)?->tasks() ?? [], static fn ($candidate): bool => $candidate->nodeName() === $task->name))[0] ?? null;

            if ($definition?->completes() !== null) {
                return $this->executor->apply($instance, $workflow, (string) $definition->completes(), SystemActor::named(SystemActor::Tasks), null, [], $correlation, $effects);
            }

            return new TransitionResult($instance->ulid, 'complete', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
        }, $expectedVersion, $idempotencyKey);
    }

    /**
     * The assignee passes their task (or approval, when the approval allows delegation) to someone eligible.
     */
    public function delegate(WorkflowTask $task, Model $actor, Model $to, ?string $comment = null): TransitionResult
    {
        return $this->runner->run('delegate', $task->instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects) use ($task, $actor, $to, $comment): TransitionResult {
            $task = $this->lockedTask($task, $instance);
            $this->active($instance);

            if (! $task->isOpen() || ! $task->isAssignedTo($actor)) {
                throw WorkflowAuthorizationException::for('delegate this task');
            }

            if ($task->isApproval() && ! ($workflow->approval($task->name)?->allowsDelegation() ?? false)) {
                throw InvalidTransitionException::because('delegation');
            }

            if ($task->delegations >= $this->config->maxDelegations) {
                throw InvalidTransitionException::because('delegation_limit');
            }

            $this->handOver($task, $instance, $workflow, $to, TaskStatus::Delegated, EventType::TaskDelegated, $actor, $correlation, $effects, ['delegations' => $task->delegations + 1, 'delegated_by' => $actor], $comment);

            return new TransitionResult($instance->ulid, 'delegate', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
        });
    }

    /**
     * A workflow manager (or a system actor) moves an open task to someone eligible.
     */
    public function reassign(WorkflowTask $task, Model|SystemActor $actor, Model $to, ?string $comment = null): TransitionResult
    {
        return $this->runner->run('reassign', $task->instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects) use ($task, $actor, $to, $comment): TransitionResult {
            $task = $this->lockedTask($task, $instance);
            $this->active($instance);
            $this->manages($instance, $workflow, $actor);

            if (! $task->isOpen()) {
                throw InvalidTransitionException::because('task_closed');
            }

            $this->handOver($task, $instance, $workflow, $to, TaskStatus::Cancelled, EventType::TaskReassigned, $actor, $correlation, $effects, ['delegations' => $task->delegations], $comment);

            return new TransitionResult($instance->ulid, 'reassign', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
        });
    }

    public function suspend(WorkflowInstance $instance, Model|SystemActor $actor, ?string $comment = null, ?int $expectedVersion = null): TransitionResult
    {
        return $this->lifecycle('suspend', $instance, $actor, $comment, $expectedVersion, InstanceStatus::Active, function (WorkflowInstance $instance): void {
            $instance->forceFill(['status' => InstanceStatus::Suspended, 'suspended_at' => now()]);
        }, EventType::Suspended);
    }

    public function resume(WorkflowInstance $instance, Model|SystemActor $actor, ?string $comment = null, ?int $expectedVersion = null): TransitionResult
    {
        return $this->lifecycle('resume', $instance, $actor, $comment, $expectedVersion, InstanceStatus::Suspended, function (WorkflowInstance $instance): void {
            $instance->forceFill(['status' => InstanceStatus::Active, 'suspended_at' => null]);
        }, EventType::Resumed);
    }

    public function cancel(WorkflowInstance $instance, Model|SystemActor $actor, ?string $comment = null, ?int $expectedVersion = null): TransitionResult
    {
        return $this->lifecycle('cancel', $instance, $actor, $comment, $expectedVersion, null, function (WorkflowInstance $instance): void {
            $instance->tasks()->open()->update(['status' => TaskStatus::Cancelled->value, 'completed_at' => now(), 'updated_at' => now()]);
            $instance->forceFill(['status' => InstanceStatus::Cancelled, 'running' => null, 'cancelled_at' => now(), 'deadline_at' => null]);
        }, EventType::Cancelled);
    }

    /**
     * The running instance of a workflow for a subject, if any.
     */
    public function running(string $workflow, Model $subject): ?WorkflowInstance
    {
        return WorkflowInstance::query()->where('workflow', $workflow)->where('subject_type', $subject->getMorphClass())->where('subject_id', Keys::string($subject->getKey()))
            ->whereIn('status', [InstanceStatus::Active->value, InstanceStatus::Suspended->value])->latest('id')->first();
    }

    /**
     * Whether this person may decide on (or work on) this task now.
     */
    public function canWorkOn(WorkflowTask $task, Model $user): bool
    {
        try {
            $instance = $task->instance;
            $this->runner->scope($instance, $user);
            $workflow = $this->definitions->forInstance($instance);

            if ($instance->status !== InstanceStatus::Active || ! $task->isOpen() || $task->state !== $instance->state) {
                return false;
            }

            if ($task->isApproval()) {
                $stage = $workflow->approval($task->name)?->stages()[$task->stage_index] ?? null;

                return $stage !== null && $this->approvals->mayDecide($task, $stage, $user, $instance, $workflow);
            }

            $this->mayWorkOn($task, $instance, $workflow, $user);

            return true;
        } catch (WorkflowException) {
            return false;
        }
    }

    /**
     * @param  \Closure(WorkflowInstance): void  $change
     */
    private function lifecycle(string $command, WorkflowInstance $instance, Model|SystemActor $actor, ?string $comment, ?int $expectedVersion, ?InstanceStatus $requires, \Closure $change, string $event): TransitionResult
    {
        return $this->runner->run($command, $instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects) use ($command, $actor, $comment, $requires, $change, $event): TransitionResult {
            $this->manages($instance, $workflow, $actor);

            if (($requires !== null && $instance->status !== $requires) || ($requires === null && ! $instance->isRunning())) {
                throw InvalidTransitionException::because($instance->isRunning() ? 'status' : 'finished');
            }

            $change($instance);
            $this->history->record($instance, $event, $actor, $correlation, $effects, from: $instance->state, metadata: ['comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 2000)]);

            if ($event === EventType::Cancelled) {
                $this->notifier->finished($instance, 'cancelled', $effects);
            }

            return new TransitionResult($instance->ulid, $command, $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
        }, $expectedVersion);
    }

    /**
     * @param  array{delegations: int, delegated_by?: Model}  $attributes
     */
    private function handOver(WorkflowTask $task, WorkflowInstance $instance, Workflow $workflow, Model $to, TaskStatus $status, string $event, Model|SystemActor $actor, string $correlation, Effects $effects, array $attributes, ?string $comment): void
    {
        $requirements = $this->requirementsOf($task, $workflow);
        $selfApproval = $task->isApproval() && ($workflow->approval($task->name)?->allowsSelfApproval($this->config->selfApproval) ?? false);

        if (($status === TaskStatus::Delegated && $actor instanceof Model && $actor->is($to)) || $task->isAssignedTo($to) || ! $this->assigner->eligible($to, $instance->tenant, $requirements) || ($task->isApproval() && ! $selfApproval && $instance->initiatedBy($to))) {
            throw InvalidTransitionException::because('assignee');
        }

        $duplicate = $instance->tasks()->open()->where('kind', $task->kind)->where('name', $task->name)->where('stage_index', $task->stage_index)->where('round', $task->round)
            ->where('assignee_type', $to->getMorphClass())->where('assignee_id', Keys::string($to->getKey()))->exists();

        if ($duplicate) {
            throw InvalidTransitionException::because('assignee');
        }

        $task->forceFill(['status' => $status, 'completed_at' => now()])->save();
        $replacement = $this->tasks->create($instance, $to, ['kind' => $task->kind, 'name' => $task->name, 'stage' => $task->stage, 'stage_index' => $task->stage_index, 'round' => $task->round, 'priority' => $task->priority, ...$attributes], $actor, $correlation, $effects);
        $replacement->forceFill(['due_at' => $task->due_at])->save();

        $this->history->record($instance, $event, $actor, $correlation, $effects, task: $task, metadata: ['task' => $task->ulid, 'replacement' => $replacement->ulid, 'comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 2000)]);
    }

    /**
     * @return list<Requirement>
     */
    public function requirementsOf(WorkflowTask $task, Workflow $workflow): array
    {
        if ($task->isApproval()) {
            return $workflow->approval($task->name)?->stages()[$task->stage_index]?->requirements() ?? [];
        }

        foreach ($workflow->state($task->state)?->tasks() ?? [] as $definition) {
            if ($definition->nodeName() === $task->name) {
                return $definition->requirements();
            }
        }

        return [];
    }

    private function mayWorkOn(WorkflowTask $task, WorkflowInstance $instance, Workflow $workflow, Model $actor): void
    {
        if (! $task->isOpen() || $task->state !== $instance->state) {
            throw InvalidTransitionException::because('task_closed');
        }

        if (! $this->authorizer->memberOf($actor, $instance->tenant)) {
            throw WorkflowAuthorizationException::for('work on this task');
        }

        if (! $task->pool) {
            if (! $task->isAssignedTo($actor)) {
                throw WorkflowAuthorizationException::for('work on this task');
            }

            return;
        }

        $requirements = $this->requirementsOf($task, $workflow);
        $allowed = $requirements === [] ? $this->authorizer->canManage($workflow, $instance, $instance->subject, $actor) : $this->authorizer->satisfies($actor, $requirements, $instance->tenant);

        if (! $allowed) {
            throw WorkflowAuthorizationException::for('work on this task');
        }
    }

    private function manages(WorkflowInstance $instance, Workflow $workflow, Model|SystemActor $actor): void
    {
        if ($actor instanceof Model && ! $this->authorizer->canManage($workflow, $instance, $instance->subject, $actor)) {
            throw WorkflowAuthorizationException::for('manage this workflow');
        }
    }

    private function active(WorkflowInstance $instance): void
    {
        if ($instance->status !== InstanceStatus::Active) {
            throw InvalidTransitionException::because($instance->status === InstanceStatus::Suspended ? 'suspended' : 'finished');
        }
    }

    private function lockedTask(WorkflowTask $task, WorkflowInstance $instance): WorkflowTask
    {
        $locked = WorkflowTask::query()->whereKey($task->id)->where('instance_id', $instance->id)->lockForUpdate()->first();

        if ($locked === null) {
            throw new WorkflowNotFoundException('The task was not found.');
        }

        $locked->setRelation('instance', $instance);

        return $locked;
    }
}
