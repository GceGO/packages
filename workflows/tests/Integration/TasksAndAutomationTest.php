<?php

declare(strict_types=1);

use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Automation\AutomationRunner;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\Jobs\ExecuteWorkflowAutomation;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Tasks\TaskStatus;
use Gcego\Workflows\Tests\Fixtures\Handlers\Recorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('creates the state\'s tasks: assigned to resolved people, or a pool', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('onboarding', $world['alpha'], $world['editor']);

    $verify = taskOf($instance, $world['reviewer2'], 'verify-documents');
    $room = taskOf($instance, null, 'prepare-room');

    expect($verify->priority)->toBe('high')->and($verify->due_at?->isFuture())->toBeTrue()->and($verify->kind)->toBe('task')
        ->and($room->pool)->toBeTrue()->and($instance->deadline_at)->not->toBeNull();
});

it('lets eligible people claim pool tasks', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('onboarding', $world['alpha'], $world['editor']);
    $room = taskOf($instance, null, 'prepare-room');

    actAs($world['reviewer1']);
    expect(fn () => engine()->claim($room, $world['reviewer1']))->toThrow(WorkflowAuthorizationException::class);

    actAs($world['reviewer3']);
    engine()->claim($room, $world['reviewer3']);

    expect($room->refresh()->status)->toBe(TaskStatus::InProgress)->and($room->isAssignedTo($world['reviewer3']))->toBeTrue()
        ->and(fn () => engine()->claim($room, $world['reviewer1']))->toThrow(WorkflowAuthorizationException::class);
});

it('completes a task, then runs its transition and the next state\'s automation', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('onboarding', $world['alpha'], $world['editor']);
    $verify = taskOf($instance, $world['reviewer2'], 'verify-documents');

    actAs($world['reviewer1']);
    expect(fn () => engine()->completeTask($verify, $world['reviewer1']))->toThrow(WorkflowAuthorizationException::class);

    actAs($world['reviewer2']);
    engine()->completeTask($verify, $world['reviewer2'], 'All present.');

    expect($instance->refresh()->state)->toBe('done')
        ->and(collect(Recorder::$calls)->pluck('handler')->all())->toBe(['FlakyHandler', 'CompleteOnboarding'])
        ->and(historyOf($instance))->toContain('task.completed', 'automation.completed')
        ->and(taskOrNone($instance))->toBe(0);

    $finish = $instance->events()->where('type', 'workflow.transitioned')->where('to_state', 'done')->first();
    expect($finish?->actor_name)->toBe('automation');
});

it('queues automation only after the transaction commits', function (): void {
    $world = world();
    Queue::fake();
    actAs($world['editor']);

    try {
        DB::transaction(static function () use ($world): void {
            engine()->start('onboarding', $world['alpha'], $world['editor']);

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
    }

    Queue::assertNothingPushed();

    engine()->start('onboarding', $world['alpha'], $world['editor']);

    Queue::assertPushedOn('workflows', ExecuteWorkflowAutomation::class, static fn (ExecuteWorkflowAutomation $job): bool => $job->automation === 'score' && $job->origin === 'state:setup' && $job->tries === 3);
});

it('runs handlers as a system actor in the instance\'s tenant, with no user', function (): void {
    $world = world();
    actAs($world['editor']);
    engine()->start('onboarding', $world['alpha'], $world['editor']);

    expect(Recorder::$calls[0])->toMatchArray(['handler' => 'FlakyHandler', 'tenant' => '1', 'current_tenant' => '1', 'actor' => 'system', 'state' => 'setup']);
});

it('retries a failing queued automation and records the failure once retries are exhausted', function (): void {
    $world = world();
    Queue::fake();
    actAs($world['editor']);
    $instance = engine()->start('onboarding', $world['alpha'], $world['editor']);
    /** @var ExecuteWorkflowAutomation $job */
    $job = Queue::pushed(ExecuteWorkflowAutomation::class)->first();
    Recorder::$failures = 5;

    expect(fn () => $job->handle(app(AutomationRunner::class)))->toThrow(RuntimeException::class, 'scoring service is down');
    expect(historyOf($instance->refresh()))->not->toContain('automation.failed');

    $job->failed(new RuntimeException('The scoring service is down.'));

    $failure = $instance->events()->where('type', 'automation.failed')->first();
    expect($failure?->metadata)->toMatchArray(['automation' => 'score', 'origin' => 'state:setup', 'error' => 'RuntimeException']);
});

it('runs each scheduled automation once, even when the job is delivered twice', function (): void {
    $world = world();
    Queue::fake();
    actAs($world['editor']);
    $instance = engine()->start('onboarding', $world['alpha'], $world['editor']);
    $job = Queue::pushed(ExecuteWorkflowAutomation::class)->first();

    $job->handle(app(AutomationRunner::class));
    $job->handle(app(AutomationRunner::class));

    expect(Recorder::$calls)->toHaveCount(1)->and($instance->events()->where('type', 'automation.completed')->count())->toBe(1);
});

it('skips state automation once the instance has left that state visit', function (): void {
    $world = world();
    Queue::fake();
    actAs($world['editor']);
    $instance = engine()->start('onboarding', $world['alpha'], $world['editor']);
    $job = Queue::pushed(ExecuteWorkflowAutomation::class)->first();
    engine()->transition($instance, 'skip', $world['editor']);

    $job->handle(app(AutomationRunner::class));

    expect(Recorder::$calls)->toBe([])->and($instance->events()->where('type', 'automation.completed')->first()?->metadata['outcome'] ?? null)->toBe('skipped');
});

it('records a failure of automation run right after the commit without failing the committed command', function (): void {
    $world = world();
    $instance = inReview($world);
    decideAs($world['reviewer1'], $instance, Decision::Approve);
    decideAs($world['reviewer2'], $instance, Decision::Approve);
    actAs($world['reviewer1']);
    engine()->transition($instance->refresh(), 'accept', $world['reviewer1']);
    Recorder::$failures = 1;

    actAs($world['editor']);
    expect(fn () => engine()->transition($instance->refresh(), 'publish', $world['editor']))->toThrow(InvalidTransitionException::class, 'comment');
    engine()->transition($instance->refresh(), 'publish', $world['editor'], 'Approved by the board.');

    expect($instance->refresh()->state)->toBe('published')->and(historyOf($instance))->toContain('automation.failed');
});

function taskOrNone(WorkflowInstance $instance): int
{
    return WorkflowTask::query()->where('instance_id', $instance->id)->open()->count();
}
