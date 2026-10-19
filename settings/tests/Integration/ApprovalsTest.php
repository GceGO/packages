<?php

declare(strict_types=1);

use Gcego\Settings\Approvals\SettingRequest;
use Gcego\Settings\Events\SettingChangeRequested;
use Gcego\Settings\History\SettingChange;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Illuminate\Support\Facades\Event;

it('turns a person\'s change into a request and applies it once someone else approves', function (): void {
    $world = world();
    actAs($world['principal'], 1);
    Event::fake([SettingChangeRequested::class]);

    $result = settings()->forTenant(1)->set('school_motto', 'Knowledge is light');
    $request = SettingRequest::query()->sole();

    expect($result->pending)->toBe(['school_motto' => $request->ulid])
        ->and($result->applied)->toBe([])
        ->and(settings()->forTenant(1)->get('school_motto'))->toBe('')
        ->and($request->value)->not->toContain('Knowledge')
        ->and($request->tenant)->toBe('institution:1');
    Event::assertDispatched(SettingChangeRequested::class);

    $instance = app(WorkflowEngine::class)->running('settings-approval', $request);
    expect(fn () => app(WorkflowEngine::class)->transition($instance, 'approve', $world['principal']))->toThrow(WorkflowAuthorizationException::class);

    actAs($world['admin'], 1);
    app(WorkflowEngine::class)->transition($instance, 'approve', $world['admin']);

    expect($request->refresh()->status)->toBe(SettingRequest::Applied)
        ->and($request->value)->toBeNull()
        ->and(settings()->forTenant(1)->get('school_motto'))->toBe('Knowledge is light')
        ->and(SettingChange::query()->where('key', 'school_motto')->orderBy('id')->pluck('change')->all())->toBe(['requested', 'approved'])
        ->and($world['principal']->notifications()->where('gcego_category', 'settings')->where('gcego_tenant', '1')->count())->toBe(1);
});

it('does not apply an approval over a value changed after the request', function (): void {
    $world = world();
    actAs($world['principal'], 1);
    settings()->forTenant(1)->set('school_motto', 'Requested');
    $request = SettingRequest::query()->sole();

    settings()->forTenant(1)->asSystem()->set('school_motto', 'Changed meanwhile');

    actAs($world['admin'], 1);
    app(WorkflowEngine::class)->transition(app(WorkflowEngine::class)->running('settings-approval', $request), 'approve', $world['admin']);

    expect($request->refresh()->status)->toBe(SettingRequest::Conflicted)
        ->and(settings()->forTenant(1)->get('school_motto'))->toBe('Changed meanwhile');
});

it('discards a refused request', function (): void {
    $world = world();
    actAs($world['principal'], 1);
    settings()->forTenant(1)->set('school_motto', 'Refuse me');
    $request = SettingRequest::query()->sole();

    actAs($world['admin'], 1);
    app(WorkflowEngine::class)->transition(app(WorkflowEngine::class)->running('settings-approval', $request), 'refuse', $world['admin']);

    expect($request->refresh()->status)->toBe(SettingRequest::Refused)
        ->and($request->value)->toBeNull()
        ->and(settings()->forTenant(1)->get('school_motto'))->toBe('');
});

it('lets trusted system code change approval-guarded settings directly', function (): void {
    settings()->forTenant(1)->asSystem('seeder')->set('school_motto', 'Seeded');

    expect(settings()->forTenant(1)->get('school_motto'))->toBe('Seeded')
        ->and(SettingRequest::query()->count())->toBe(0);
});

it('applies directly when approvals are switched off', function (): void {
    $this->reboot(['gcego-settings.approvals.enabled' => false]);
    $world = world();
    actAs($world['principal'], 1);

    expect(settings()->forTenant(1)->set('school_motto', 'Direct')->applied)->toHaveKey('school_motto');
});
