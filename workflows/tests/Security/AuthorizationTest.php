<?php

declare(strict_types=1);

use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\Exceptions\WorkflowDefinitionException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Models\WorkflowEvent;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Tests\Fixtures\Models\Membership;

it('never lets people run system-only transitions', function (): void {
    $world = world();
    $instance = inReview($world);
    actAs($world['admin']);

    expect(fn () => engine()->transition($instance, 'reject', $world['admin']))->toThrow(WorkflowAuthorizationException::class)
        ->and($instance->refresh()->state)->toBe('under-review');
});

it('limits system actors to the transitions that name them or the definition wires to them', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    expect(fn () => engine()->transition($instance, 'submit', SystemActor::named('deadlines')))->toThrow(WorkflowAuthorizationException::class)
        ->and(fn () => engine()->transition($instance, 'withdraw', SystemActor::named('importer'), 'Bulk cleanup'))->toThrow(WorkflowAuthorizationException::class)
        ->and(fn () => SystemActor::named('Robert"); DROP'))->toThrow(WorkflowDefinitionException::class);

    engine()->transition($instance, 'submit', $world['editor']);
    engine()->transition($instance, 'expire', SystemActor::named('deadlines'));
    expect($instance->refresh()->state)->toBe('expired');
});

it('denies starting without the subject policy, for locked records and for models without a policy', function (): void {
    $world = world();

    actAs($world['visitor']);
    expect(fn () => engine()->start('registration', $world['alpha'], $world['visitor']))->toThrow(WorkflowAuthorizationException::class);

    actAs($world['editor']);
    expect(fn () => engine()->start('registration', $world['locked'], $world['editor']))->toThrow(WorkflowAuthorizationException::class)
        ->and(fn () => engine()->start('leave', Membership::query()->firstOrFail(), $world['editor']))->toThrow(WorkflowAuthorizationException::class)
        ->and(fn () => engine()->start('registration', $world['editor'], $world['editor']))->toThrow(WorkflowDefinitionException::class, 'cannot run for this subject');
});

it('hides other tenants\' instances and refuses their commands as missing', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);

    actAs($world['south'], 2);
    expect(fn () => app(WorkflowRepository::class)->find($instance->ulid, $world['south']))->toThrow(WorkflowNotFoundException::class)
        ->and(fn () => engine()->decide($task, Decision::Approve, $world['south']))->toThrow(WorkflowNotFoundException::class)
        ->and(fn () => engine()->cancel($instance, $world['south']))->toThrow(WorkflowNotFoundException::class)
        ->and(engine()->available($instance, $world['south']))->toBe([]);

    // A member of both tenants, working in the other one, does not see it either.
    Membership::query()->create(['user_id' => $world['admin']->id, 'institution_id' => 2]);
    actAs($world['admin'], 2);
    expect(fn () => app(WorkflowRepository::class)->find($instance->ulid, $world['admin']))->toThrow(WorkflowNotFoundException::class);
});

it('still checks tenant membership when tenant scoping of queries is switched off', function (): void {
    $this->reboot(['gcego-workflows.security.enforce_tenant_scope' => false]);
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);

    actAs($world['south'], 2);
    expect(fn () => engine()->decide($task, Decision::Approve, $world['south']))->toThrow(WorkflowAuthorizationException::class)
        ->and(app(WorkflowRepository::class)->canView($instance, $world['south']))->toBeFalse();
});

it('lets only managers and the subject\'s updaters manage', function (): void {
    $world = world();
    $instance = inReview($world);

    actAs($world['reviewer1']);
    expect(fn () => engine()->cancel($instance, $world['reviewer1']))->toThrow(WorkflowAuthorizationException::class);

    actAs($world['admin']);
    engine()->cancel($instance, $world['admin'], 'Fraudulent application.');
    expect($instance->refresh()->isRunning())->toBeFalse();
});

it('ignores state or actor values smuggled into command input', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);

    engine()->transition($instance, 'submit', $world['editor'], input: ['state' => 'published', 'to' => 'published', 'actor' => 'system', 'user_id' => $world['admin']->id]);

    $event = $instance->events()->where('type', 'workflow.transitioned')->firstOrFail();
    expect($instance->refresh()->state)->toBe('submitted')->and($event->actor_kind)->toBe('user')->and($event->getAttribute('actor_id'))->toBe((string) $world['editor']->id);
});

it('keeps the history append-only', function (): void {
    $world = world();
    actAs($world['editor']);
    $instance = engine()->start('registration', $world['alpha'], $world['editor']);
    $event = WorkflowEvent::query()->where('instance_id', $instance->id)->firstOrFail();

    expect(fn () => $event->forceFill(['type' => 'workflow.completed'])->save())->toThrow(WorkflowDefinitionException::class, 'append-only')
        ->and(fn () => $event->delete())->toThrow(WorkflowDefinitionException::class, 'append-only')
        ->and($event->refresh()->type)->toBe('workflow.started');
});

it('keeps internal identifiers out of serialized models', function (): void {
    $world = world();
    $instance = inReview($world);
    $task = taskOf($instance, $world['reviewer1']);

    expect(array_keys($instance->toArray()))->not->toContain('tenant', 'subject_id', 'subject_type', 'initiator_id', 'id')
        ->and(array_keys($task->toArray()))->not->toContain('tenant', 'assignee_id', 'instance_id')
        ->and($instance->getRouteKeyName())->toBe('ulid');
});

it('only resolves registered workflow names', function (string $name): void {
    expect(fn () => app(WorkflowDefinitions::class)->resolve($name))->toThrow(WorkflowNotFoundException::class);
})->with(['../../etc/passwd', 'page:home', 'REGISTRATION', 'unknown', '']);

it('sends guests to sign in and keeps panels\' workflow pages behind their access checks', function (): void {
    $world = world();
    $instance = inReview($world);

    auth()->logout();
    app('auth')->forgetGuards();

    $this->get('/desk/workflows')->assertRedirect('/login');
    $this->get('/desk/workflows/'.$instance->ulid)->assertRedirect('/login');
});
