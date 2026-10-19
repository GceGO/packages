<?php

declare(strict_types=1);

use Gcego\Security\Tenancy\TenantContext;
use Gcego\Workflows\Approvals\ApprovalCoordinator;
use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Models\WorkflowDecision;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Tasks\TaskStatus;
use Gcego\Workflows\Tests\Fixtures\Models\User;

it('assigns one approval task per eligible reviewer, never other tenants or the initiator', function (): void {
    $world = world();
    $instance = inReview($world);

    $assignees = WorkflowTask::query()->where('instance_id', $instance->id)->where('kind', 'approval')->pluck('assignee_id')->all();

    expect($assignees)->toEqualCanonicalizing([(string) $world['reviewer1']->id, (string) $world['reviewer2']->id, (string) $world['reviewer3']->id])
        ->and(WorkflowTask::query()->where('instance_id', $instance->id)->first()?->due_at?->isFuture())->toBeTrue()
        ->and(historyOf($instance))->toContain('approval.opened');
});

it('approves a quorum and closes the remaining reviewers\' tasks', function (): void {
    $world = world();
    $instance = inReview($world);

    decideAs($world['reviewer1'], $instance, Decision::Approve, 'Accredited.');
    expect(app(ApprovalCoordinator::class)->outcome($instance->refresh(), 'academic-review'))->toBeNull();

    decideAs($world['reviewer2'], $instance, Decision::Approve);

    expect(app(ApprovalCoordinator::class)->outcome($instance->refresh(), 'academic-review'))->toBe('approved')
        ->and(taskOrNull($instance, $world['reviewer3']))->toBeNull()
        ->and(WorkflowTask::query()->where('assignee_id', (string) $world['reviewer3']->id)->first()?->status)->toBe(TaskStatus::Cancelled)
        ->and(WorkflowDecision::query()->count())->toBe(2);
});

it('rejects on the first rejection, running the outcome transition as the approvals system actor', function (): void {
    $world = world();
    $instance = inReview($world);

    decideAs($world['reviewer2'], $instance, Decision::Reject, 'Missing accreditation.');

    $last = $instance->events()->where('type', 'workflow.transitioned')->orderByDesc('sequence')->first();

    expect($instance->refresh()->state)->toBe('rejected')->and($instance->status)->toBe(InstanceStatus::Completed)
        ->and($last?->actor_kind)->toBe('system')->and($last?->actor_name)->toBe('approvals')
        ->and($instance->tasks()->open()->count())->toBe(0);
});

it('requires a comment to reject or request changes', function (): void {
    $world = world();
    $instance = inReview($world);

    expect(fn () => decideAs($world['reviewer1'], $instance, Decision::Reject))->toThrow(InvalidTransitionException::class, 'Please add a comment.')
        ->and(fn () => decideAs($world['reviewer1'], $instance, Decision::Changes, ' '))->toThrow(InvalidTransitionException::class, 'Please add a comment.')
        ->and(WorkflowDecision::query()->count())->toBe(0);
});

it('returns for changes and opens a new round whose earlier decisions no longer count', function (): void {
    $world = world();
    $instance = inReview($world);
    decideAs($world['reviewer1'], $instance, Decision::Approve);
    decideAs($world['reviewer2'], $instance, Decision::Changes, 'Add the floor plan.');

    expect($instance->refresh()->state)->toBe('returned');

    actAs($world['editor']);
    engine()->transition($instance, 'submit', $world['editor']);
    actAs($world['reviewer1']);
    engine()->transition($instance, 'review', $world['reviewer1']);

    $round = WorkflowTask::query()->where('instance_id', $instance->id)->open()->get();
    expect($round)->toHaveCount(3)->and($round->pluck('round')->unique()->all())->toBe([2]);

    decideAs($world['reviewer1'], $instance, Decision::Approve);
    expect(app(ApprovalCoordinator::class)->outcome($instance->refresh(), 'academic-review'))->toBeNull();
});

it('lets each reviewer decide once', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);
    decideAs($world['reviewer1'], $instance, Decision::Approve);

    actAs($world['reviewer1']);
    expect(fn () => engine()->decide($task->refresh(), Decision::Reject, $world['reviewer1'], 'Changed my mind'))->toThrow(InvalidTransitionException::class, 'no longer open');
});

it('refuses decisions on someone else\'s task', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);

    actAs($world['reviewer2']);
    expect(fn () => engine()->decide($task, Decision::Approve, $world['reviewer2']))->toThrow(WorkflowAuthorizationException::class);
});

it('treats abstentions as neither approval nor rejection, rejecting when the quorum becomes unreachable', function (): void {
    $world = world();
    $instance = inReview($world);
    decideAs($world['reviewer1'], $instance, Decision::Abstain);
    decideAs($world['reviewer2'], $instance, Decision::Abstain);

    expect($instance->refresh()->state)->toBe('rejected')
        ->and($instance->events()->where('type', 'approval.completed')->first()?->metadata['outcome'] ?? null)->toBe('rejected');
});

it('runs sequential stages in order: an assigned manager, then a pool of HR approvers', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('leave', $world['alpha'], $world['editor']);

    expect(WorkflowTask::query()->where('instance_id', $instance->id)->open()->get()->map(fn ($task) => $task->stage)->all())->toBe(['manager']);

    decideAs($world['reviewer1'], $instance, Decision::Approve);
    $pool = taskOf($instance, null);
    expect($pool->stage)->toBe('hr')->and($pool->pool)->toBeTrue()->and($instance->refresh()->state)->toBe('requested');

    actAs($world['visitor']);
    expect(fn () => engine()->decide($pool, Decision::Approve, $world['visitor']))->toThrow(WorkflowAuthorizationException::class);

    decideAs($world['reviewer3'], $instance, Decision::Approve, pool: true);

    expect($instance->refresh()->state)->toBe('granted')->and(historyOf($instance))->toContain('approval.stage_completed');
});

it('never lets other tenants decide on pool approvals', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('leave', $world['alpha'], $world['editor']);
    decideAs($world['reviewer1'], $instance, Decision::Approve);
    $pool = taskOf($instance, null);

    actAs($world['south'], 2);
    expect(fn () => engine()->decide($pool, Decision::Approve, $world['south']))->toThrow(WorkflowNotFoundException::class);

    // Even in tenant 1's context (no membership): refused.
    actAs($world['south'], null);
    app(TenantContext::class);
    expect(engine()->canWorkOn($pool, $world['south']))->toBeFalse();
});

it('forbids deciding on your own request unless the approval allows it', function (): void {
    $world = world();
    actAs($world['admin']);
    $instance = engine()->start('leave', $world['alpha'], $world['admin']);
    decideAs($world['reviewer1'], $instance, Decision::Approve);

    expect(fn () => decideAs($world['admin'], $instance, Decision::Approve, pool: true))->toThrow(InvalidTransitionException::class, 'your own request');

    decideAs($world['reviewer2'], $instance, Decision::Approve, pool: true);
    expect($instance->refresh()->state)->toBe('granted');
});

it('delegates an approval to an eligible person, within the delegation limit', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);

    actAs($world['reviewer1']);
    expect(fn () => engine()->delegate($task, $world['reviewer1'], $world['south']))->toThrow(InvalidTransitionException::class, 'cannot take this work')
        ->and(fn () => engine()->delegate($task, $world['reviewer1'], $world['editor']))->toThrow(InvalidTransitionException::class, 'cannot take this work')
        ->and(fn () => engine()->delegate($task, $world['reviewer1'], $world['reviewer2']))->toThrow(InvalidTransitionException::class, 'cannot take this work');

    engine()->delegate($task, $world['reviewer1'], $world['admin'], 'On leave this week.');

    expect($task->refresh()->status)->toBe(TaskStatus::Delegated)
        ->and(taskOf($instance, $world['admin'])->delegations)->toBe(1)
        ->and(historyOf($instance->refresh()))->toContain('task.delegated');

    decideAs($world['admin'], $instance, Decision::Approve);
    decideAs($world['reviewer2'], $instance, Decision::Approve);
    expect(app(ApprovalCoordinator::class)->outcome($instance->refresh(), 'academic-review'))->toBe('approved');
});

it('refuses delegation where the approval does not allow it, or past the limit', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('leave', $world['alpha'], $world['editor']);
    $task = taskOf($instance, $world['reviewer1']);

    actAs($world['reviewer1']);
    expect(fn () => engine()->delegate($task, $world['reviewer1'], $world['reviewer2']))->toThrow(InvalidTransitionException::class, 'cannot be passed on');

    $this->reboot(['gcego-workflows.approvals.max_delegations' => 0]);
    $world = world();
    $instance = inReview($world);
    actAs($world['reviewer1']);
    expect(fn () => engine()->delegate(taskOf($instance, $world['reviewer1']), $world['reviewer1'], $world['admin']))->toThrow(InvalidTransitionException::class, 'too many times');
});

it('lets managers reassign open work to eligible people only', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer3']);

    actAs($world['reviewer1']);
    expect(fn () => engine()->reassign($task, $world['reviewer1'], $world['admin']))->toThrow(WorkflowAuthorizationException::class);

    actAs($world['admin']);
    engine()->reassign($task, $world['admin'], $world['admin']);

    expect($task->refresh()->status)->toBe(TaskStatus::Cancelled)->and(taskOf($instance, $world['admin'])->name)->toBe('academic-review')
        ->and(historyOf($instance->refresh()))->toContain('task.reassigned');
});

function taskOrNull(WorkflowInstance $instance, User $user): ?WorkflowTask
{
    try {
        return taskOf($instance, $user);
    } catch (RuntimeException) {
        return null;
    }
}
