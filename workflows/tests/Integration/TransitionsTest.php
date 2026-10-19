<?php

declare(strict_types=1);

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Engine\TransitionResult;
use Gcego\Workflows\Events\TransitionCompleted;
use Gcego\Workflows\Events\WorkflowCommandRefused;
use Gcego\Workflows\Events\WorkflowEventRecorded;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\Exceptions\WorkflowConflictException;
use Gcego\Workflows\Exceptions\WorkflowDefinitionException;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Models\WorkflowEvent;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Tests\Fixtures\Handlers\ConcurrentWriter;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('starts an instance in the initial state with its version, structure, initiator and tenant', function (): void {
    $world = world();
    actAs($world['editor']);

    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    $definition = app(WorkflowDefinitions::class)->resolve('registration');

    expect($instance->state)->toBe('draft')->and($instance->status)->toBe(InstanceStatus::Active)
        ->and($instance->version)->toBe(2)->and($instance->structure)->toBe($definition->structureHash())
        ->and($instance->tenant)->toBe('1')->and($instance->initiatedBy($world['editor']))->toBeTrue()
        ->and(historyOf($instance))->toBe(['workflow.started']);
});

it('runs one instance per subject unless the workflow allows concurrent instances', function (): void {
    $world = world();
    actAs($world['editor']);
    engine()->start('registration', $world['alpha'], $world['editor']);

    expect(fn () => engine()->start('registration', $world['alpha'], $world['editor']))->toThrow(WorkflowConflictException::class);

    engine()->start('onboarding', $world['alpha'], $world['editor']);
    engine()->start('onboarding', $world['alpha'], $world['editor']);

    expect(WorkflowInstance::query()->where('workflow', 'onboarding')->count())->toBe(2);
});

it('refuses a transition its guard denies, with the guard reason, writing nothing', function (): void {
    $world = world();
    $world['alpha']->forceFill(['documents' => false])->save();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    $version = $instance->lock_version;
    Event::fake([WorkflowCommandRefused::class]);

    expect(fn () => engine()->transition($instance, 'submit', $world['editor']))->toThrow(InvalidTransitionException::class, 'Upload the registration documents first.');
    expect($instance->refresh()->lock_version)->toBe($version)->and($instance->state)->toBe('draft')->and(historyOf($instance))->toBe(['workflow.started']);
    Event::assertDispatched(WorkflowCommandRefused::class, static fn (WorkflowCommandRefused $event): bool => $event->reason === 'guard' && $event->command === 'transition:submit');
});

it('follows the first branch whose condition passes', function (): void {
    $world = world();
    $world['alpha']->forceFill(['complete' => false])->save();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    engine()->transition($instance, 'submit', $world['editor']);
    actAs($world['reviewer1']);

    $result = engine()->transition($instance, 'review', $world['reviewer1']);

    expect($result->to)->toBe('returned')->and($instance->refresh()->state)->toBe('returned');
});

it('only runs transitions that leave the current server-side state', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    expect(fn () => engine()->transition($instance, 'publish', $world['editor']))->toThrow(InvalidTransitionException::class, 'is not possible while this is "draft"')
        ->and(fn () => engine()->transition($instance, 'no-such-transition', $world['editor']))->toThrow(InvalidTransitionException::class);
});

it('requires comments where the transition asks for one', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    expect(fn () => engine()->transition($instance, 'withdraw', $world['editor'], '  '))->toThrow(InvalidTransitionException::class, 'Please add a comment.');

    engine()->transition($instance, 'withdraw', $world['editor'], 'Duplicate application.');

    expect($instance->refresh()->state)->toBe('expired')->and($instance->events()->where('type', 'workflow.transitioned')->first()?->metadata['comment'] ?? null)->toBe('Duplicate application.');
});

it('waits for a required approval before running its transition', function (): void {
    $world = world();
    $instance = inReview($world);

    expect(fn () => engine()->transition($instance, 'accept', $world['reviewer1']))->toThrow(InvalidTransitionException::class, 'Waiting for "Academic review" to be approved.');

    decideAs($world['reviewer1'], $instance, Decision::Approve);
    decideAs($world['reviewer2'], $instance, Decision::Approve);
    actAs($world['reviewer3']);
    engine()->transition($instance->refresh(), 'accept', $world['reviewer3']);

    expect($instance->refresh()->state)->toBe('approved');
});

it('completes at a terminal state and only leaves it through reopen transitions', function (): void {
    $world = world();
    $instance = inReview($world);
    decideAs($world['reviewer1'], $instance, Decision::Reject, 'Not accredited.');

    $instance->refresh();
    expect($instance->state)->toBe('rejected')->and($instance->status)->toBe(InstanceStatus::Completed)->and($instance->completed_at)->not->toBeNull()
        ->and(DB::table('gcego_workflow_instances')->where('id', $instance->id)->value('running'))->toBeNull();

    actAs($world['editor']);
    expect(fn () => engine()->transition($instance, 'submit', $world['editor']))->toThrow(InvalidTransitionException::class, 'has finished');

    engine()->transition($instance, 'reopen', $world['editor']);

    expect($instance->refresh()->state)->toBe('draft')->and($instance->status)->toBe(InstanceStatus::Active)->and(historyOf($instance))->toContain('workflow.reopened');
});

it('lists the transitions a person may run, explaining blocked ones', function (): void {
    $world = world();
    $world['alpha']->forceFill(['documents' => false])->save();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    $available = engine()->available($instance, $world['editor']);
    $byName = collect($available)->keyBy(fn ($option) => $option->transition->nodeName());

    expect($byName->keys()->all())->toBe(['submit', 'withdraw'])
        ->and($byName['submit']->enabled())->toBeFalse()->and($byName['submit']->reason)->toBe('Upload the registration documents first.')
        ->and($byName['withdraw']->enabled())->toBeTrue();

    actAs($world['visitor']);
    expect(engine()->available($instance, $world['visitor']))->toBe([]);
});

it('replays idempotent commands instead of running them twice', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor'], idempotencyKey: 'form-1');
    $again = engine()->start('registration', $world['alpha'], $world['editor'], idempotencyKey: 'form-1');

    $first = engine()->transition($instance, 'submit', $world['editor'], idempotencyKey: 'click-1');
    $second = engine()->transition($instance, 'submit', $world['editor'], idempotencyKey: 'click-1');

    expect($again->ulid)->toBe($instance->ulid)->and(WorkflowInstance::query()->count())->toBe(1)
        ->and($first->replayed)->toBeFalse()->and($second->replayed)->toBeTrue()->and($second->toArray())->toBe($first->toArray())
        ->and(historyOf($instance->refresh()))->toBe(['workflow.started', 'workflow.transitioned']);
});

it('refuses commands from stale screens (optimistic locking)', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    $seen = $instance->lock_version;
    engine()->transition($instance, 'submit', $world['editor'], expectedVersion: $seen);

    expect(fn () => engine()->transition($instance, 'withdraw', $world['editor'], 'Old screen', expectedVersion: $seen))->toThrow(WorkflowConflictException::class)
        ->and($instance->refresh()->state)->toBe('submitted');
});

it('rolls back a transition when another process changed the instance meanwhile', function (): void {
    $world = world();
    $workflow = new Workflow('race', new State('open'), new State('closed', terminal: true), new Transition('close', 'open', 'closed', guard: ConcurrentWriter::class));
    app(RegistryInterface::class)->register($workflow);
    actAs($world['editor']);
    $instance = engine()->start('race', $world['alpha'], $world['editor']);
    $events = WorkflowEvent::query()->count();

    expect(fn () => engine()->transition($instance, 'close', $world['editor']))->toThrow(WorkflowConflictException::class);
    expect(DB::table('gcego_workflow_instances')->where('id', $instance->id)->value('state'))->toBe('open')
        ->and(WorkflowEvent::query()->count())->toBe($events);
});

it('continues instances of compatible versions and refuses incompatible ones', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    DB::table('gcego_workflow_instances')->where('id', $instance->id)->update(['version' => 1]);
    engine()->transition($instance->refresh(), 'submit', $world['editor']);
    expect($instance->refresh()->state)->toBe('submitted');

    DB::table('gcego_workflow_instances')->where('id', $instance->id)->update(['version' => 7]);
    expect(fn () => engine()->transition($instance->refresh(), 'withdraw', $world['editor'], 'Too late'))->toThrow(WorkflowDefinitionException::class, 'cannot continue an instance of version 7');

    DB::table('gcego_workflow_instances')->where('id', $instance->id)->update(['version' => 1, 'state' => 'retired-state']);
    expect(fn () => engine()->transition($instance->refresh(), 'withdraw', $world['editor'], 'Gone'))->toThrow(WorkflowDefinitionException::class);
});

it('suspends, resumes and cancels for managers only', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    actAs($world['visitor']);
    expect(fn () => engine()->suspend($instance, $world['visitor']))->toThrow(WorkflowAuthorizationException::class);

    actAs($world['admin']);
    engine()->suspend($instance, $world['admin'], 'Audit');
    actAs($world['editor']);
    expect(fn () => engine()->transition($instance, 'submit', $world['editor']))->toThrow(InvalidTransitionException::class, 'suspended');

    actAs($world['admin']);
    engine()->resume($instance, $world['admin']);
    engine()->cancel($instance, $world['admin'], 'Closed by the registrar.');

    expect($instance->refresh()->status)->toBe(InstanceStatus::Cancelled)->and($instance->isRunning())->toBeFalse()
        ->and(historyOf($instance))->toBe(['workflow.started', 'workflow.suspended', 'workflow.resumed', 'workflow.cancelled'])
        ->and(fn () => engine()->resume($instance, $world['admin']))->toThrow(InvalidTransitionException::class, 'finished');

    actAs($world['editor']);
    expect(engine()->start('registration', $world['alpha'], $world['editor'])->state)->toBe('draft');
});

it('releases events only after the transaction commits, and never after a rollback', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    Event::fake([TransitionCompleted::class, WorkflowEventRecorded::class]);

    try {
        DB::transaction(static function () use ($instance, $world): void {
            engine()->transition($instance, 'submit', $world['editor']);
            Event::assertNotDispatched(TransitionCompleted::class);

            throw new RuntimeException('The surrounding request failed.');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched(TransitionCompleted::class);
    expect($instance->refresh()->state)->toBe('draft');

    $result = engine()->transition($instance, 'submit', $world['editor']);

    Event::assertDispatched(TransitionCompleted::class, static fn (TransitionCompleted $event): bool => $event->result instanceof TransitionResult && $event->result->to === 'submitted' && $event->tenant === '1');
    Event::assertDispatched(WorkflowEventRecorded::class, static fn (WorkflowEventRecorded $event): bool => $event->type === 'workflow.transitioned' && $event->correlation === $result->correlation);
});

it('appends history in sequence with a correlation per command', function (): void {
    $world = world();
    $instance = inReview($world);

    $events = $instance->events()->orderBy('sequence')->get();

    expect($events->pluck('sequence')->all())->toBe(range(1, $events->count()))
        ->and($events->where('type', 'approval.opened')->first()?->correlation)->toBe($events->where('type', 'workflow.transitioned')->last()?->correlation);
});
