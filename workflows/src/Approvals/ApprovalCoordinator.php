<?php

declare(strict_types=1);

namespace Gcego\Workflows\Approvals;

use Gcego\Workflows\Access\WorkflowAuthorizer;
use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Engine\Effects;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\History\EventType;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Models\WorkflowDecision;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Tasks\TaskAssigner;
use Gcego\Workflows\Tasks\TaskFactory;
use Gcego\Workflows\Tasks\TaskStatus;
use Gcego\Workflows\Workflow;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Opens approvals (a new round each time their state is entered), opens
 * stages in order, records decisions and resolves stages:
 *
 * - a rejection rejects the approval, a request for changes returns it;
 * - enough approvals (ApprovalPolicy) pass the stage, then the next stage
 *   opens or the approval is approved;
 * - when the undecided reviewers can no longer reach the policy (assigned
 *   stages), it is rejected as "insufficient".
 *
 * Pool stages (no reviewers resolver, or none eligible) accept decisions
 * from anyone who satisfies the stage's requirements, or, when it has
 * none, from workflow managers only. Runs inside the command transaction.
 */
final readonly class ApprovalCoordinator
{
    public function __construct(
        private TaskAssigner $assigner,
        private TaskFactory $tasks,
        private HistoryRecorder $history,
        private WorkflowAuthorizer $authorizer,
        private WorkflowsConfig $config,
    ) {}

    public function open(Approval $approval, WorkflowInstance $instance, Workflow $workflow, Model|SystemActor|null $actor, string $correlation, Effects $effects): void
    {
        $round = (Keys::int($instance->tasks()->where('kind', WorkflowTask::Approval)->where('name', $approval->nodeName())->max('round'))) + 1;

        $this->history->record($instance, EventType::ApprovalOpened, $actor, $correlation, $effects, metadata: ['approval' => $approval->nodeName(), 'round' => $round, 'stages' => count($approval->stages())]);
        $this->openStage($approval, 0, $round, $instance, $workflow, $actor, $correlation, $effects);
    }

    /**
     * Records a decision. Returns the outcome transition to run (as the
     * "approvals" system actor), or null.
     */
    public function decide(WorkflowTask $task, Decision $decision, Model $user, ?string $comment, WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects): ?string
    {
        $approval = $workflow->approval($task->name);
        $stage = $approval === null ? null : ($approval->stages()[$task->stage_index] ?? null);

        if ($approval === null || $stage === null || ! $task->isApproval() || ! $task->isOpen() || $task->state !== $instance->state || $task->round !== $this->currentRound($instance, $approval)) {
            throw InvalidTransitionException::because('task_closed');
        }

        if (! $this->mayDecide($task, $stage, $user, $instance, $workflow)) {
            throw WorkflowAuthorizationException::for('decide on this approval');
        }

        if ($instance->initiatedBy($user) && ! $approval->allowsSelfApproval($this->config->selfApproval)) {
            throw InvalidTransitionException::because('self_approval');
        }

        if ($decision === Decision::Abstain && ! $approval->allowsAbstaining()) {
            throw InvalidTransitionException::because('abstain');
        }

        if (($comment === null || trim($comment) === '') && (($decision === Decision::Reject && $this->config->commentOnRejection) || ($decision === Decision::Changes && $this->config->commentOnChanges))) {
            throw InvalidTransitionException::because('comment');
        }

        try {
            $record = new WorkflowDecision;
            $record->forceFill([
                'instance_id' => $instance->id,
                'task_id' => $task->id,
                'tenant' => $instance->tenant,
                'approval' => $approval->nodeName(),
                'stage' => $stage->nodeName(),
                'round' => $task->round,
                'actor_type' => $user->getMorphClass(),
                'actor_id' => Keys::string($user->getKey()),
                'decision' => $decision,
                'comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 2000),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw InvalidTransitionException::because('already_decided');
        }

        if (! $task->pool) {
            $task->forceFill(['status' => TaskStatus::Completed, 'completed_at' => now()])->save();
        }

        $this->history->record($instance, EventType::Decided, $user, $correlation, $effects, task: $task, metadata: ['approval' => $approval->nodeName(), 'stage' => $stage->nodeName(), 'round' => $task->round, 'decision' => $decision->value, 'comment' => $record->comment]);

        return $this->resolve($approval, $stage, $task, $instance, $workflow, $correlation, $effects);
    }

    public function mayDecide(WorkflowTask $task, Stage $stage, Model $user, WorkflowInstance $instance, Workflow $workflow): bool
    {
        if (! $this->authorizer->memberOf($user, $instance->tenant)) {
            return false;
        }

        if (! $task->pool) {
            return $task->isAssignedTo($user);
        }

        if ($stage->requirements() === []) {
            return $this->authorizer->canManage($workflow, $instance, $instance->subject, $user);
        }

        return $this->authorizer->satisfies($user, $stage->requirements(), $instance->tenant)
            && ! WorkflowDecision::query()->where('task_id', $task->id)->where('actor_type', $user->getMorphClass())->where('actor_id', Keys::string($user->getKey()))->exists();
    }

    public function currentRound(WorkflowInstance $instance, Approval $approval): int
    {
        return Keys::int($instance->tasks()->where('kind', WorkflowTask::Approval)->where('name', $approval->nodeName())->max('round'));
    }

    /**
     * The outcome of the latest round entered during the current state visit ("approved", "rejected", "changes"), or null.
     */
    public function outcome(WorkflowInstance $instance, string $approval): ?string
    {
        $event = $instance->events()->where('type', EventType::ApprovalCompleted)->where('sequence', '>', $instance->state_sequence)->orderByDesc('sequence')->get()
            ->first(static fn ($event): bool => ($event->metadata['approval'] ?? null) === $approval);
        $outcome = $event?->metadata['outcome'] ?? null;

        return is_string($outcome) ? $outcome : null;
    }

    private function openStage(Approval $approval, int $index, int $round, WorkflowInstance $instance, Workflow $workflow, Model|SystemActor|null $actor, string $correlation, Effects $effects): void
    {
        $stage = $approval->stages()[$index];
        $context = new WorkflowContext($instance, $workflow, $actor);
        $reviewers = $stage->reviewers() === null ? [] : $this->assigner->resolve($stage->reviewers(), $context, $stage->requirements(), ! $approval->allowsSelfApproval($this->config->selfApproval));
        $attributes = ['kind' => WorkflowTask::Approval, 'name' => $approval->nodeName(), 'stage' => $stage->nodeName(), 'stage_index' => $index, 'round' => $round, 'due' => $approval->due(), 'priority' => 'normal'];

        if ($reviewers === []) {
            $this->tasks->create($instance, null, $attributes, $actor, $correlation, $effects);

            return;
        }

        foreach ($reviewers as $reviewer) {
            $this->tasks->create($instance, $reviewer, $attributes, $actor, $correlation, $effects);
        }
    }

    private function resolve(Approval $approval, Stage $stage, WorkflowTask $task, WorkflowInstance $instance, Workflow $workflow, string $correlation, Effects $effects): ?string
    {
        $decisions = WorkflowDecision::query()->where('instance_id', $instance->id)->where('approval', $approval->nodeName())->where('stage', $stage->nodeName())->where('round', $task->round)->pluck('decision')->all();
        $count = static fn (Decision $kind): int => count(array_filter($decisions, static fn (mixed $decision): bool => $decision === $kind));
        $stageTasks = $instance->tasks()->where('kind', WorkflowTask::Approval)->where('name', $approval->nodeName())->where('stage_index', $task->stage_index)->where('round', $task->round);

        $outcome = null;

        if ($count(Decision::Reject) > 0) {
            $outcome = 'rejected';
        } elseif ($count(Decision::Changes) > 0) {
            $outcome = 'changes';
        } else {
            // Live reviewers: open, working or decided (handed-over and escalated rows were replaced).
            $live = (clone $stageTasks)->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value, TaskStatus::Completed->value]);
            $pool = (clone $live)->where('pool', true)->exists();
            $reviewers = $pool ? null : (clone $live)->count() - $count(Decision::Abstain);
            $needed = $stage->policy()->needed($stage->requiredApprovals(), $reviewers);

            if ($reviewers !== null && $reviewers <= 0) {
                $outcome = 'rejected';
            } elseif ($count(Decision::Approve) >= $needed) {
                $outcome = 'stage';
            } elseif ($reviewers !== null && $count(Decision::Approve) + (clone $stageTasks)->open()->count() < $needed) {
                $outcome = 'rejected';
            }
        }

        if ($outcome === null) {
            return null;
        }

        (clone $stageTasks)->open()->update(['status' => TaskStatus::Cancelled->value, 'completed_at' => now(), 'updated_at' => now()]);
        $approvals = SystemActor::named(SystemActor::Approvals);

        if ($outcome === 'stage') {
            $this->history->record($instance, EventType::ApprovalStageCompleted, $approvals, $correlation, $effects, metadata: ['approval' => $approval->nodeName(), 'stage' => $stage->nodeName(), 'round' => $task->round]);

            if (isset($approval->stages()[$task->stage_index + 1])) {
                $this->openStage($approval, $task->stage_index + 1, $task->round, $instance, $workflow, $approvals, $correlation, $effects);

                return null;
            }

            $outcome = 'approved';
        }

        $this->history->record($instance, EventType::ApprovalCompleted, $approvals, $correlation, $effects, metadata: ['approval' => $approval->nodeName(), 'round' => $task->round, 'outcome' => $outcome]);

        return match ($outcome) {
            'approved' => $approval->onApprove(),
            'rejected' => $approval->onReject(),
            default => $approval->onChanges(),
        };
    }
}
