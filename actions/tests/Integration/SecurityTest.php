<?php

declare(strict_types=1);

use Gcego\Actions\Confirmation\ConfirmationManager;
use Gcego\Actions\Create;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Actions\Tests\Fixtures\Models\Membership;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Gcego\Actions\Tests\Fixtures\Models\User;
use Gcego\Blueprints\Blueprint;
use Gcego\Data\Source;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Ability;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
});

it('cannot execute an action of another definition or an unknown one', function (): void {
    actAs($this->world['admin']);

    expect(act('export')->status)->toBe(ActionStatus::NotFound)
        ->and(act('export', definition: 'blueprint:reports')->successful())->toBeTrue()
        ->and(act('does-not-exist')->status)->toBe(ActionStatus::NotFound)
        ->and(act('../delete')->status)->toBe(ActionStatus::NotFound)
        ->and(act('delete', definition: 'blueprint:missing')->status)->toBe(ActionStatus::NotFound);
});

it('cannot act on another institution\'s record, even with a valid confirmation flow', function (): void {
    actAs($this->world['admin']);
    $south = $this->world['subjects']['south'];

    expect(confirmAndAct('delete', ['record' => $south->id])->status)->toBe(ActionStatus::NotFound)
        ->and(act('edit', ['record' => $south->id, 'input' => ['name' => 'x']])->status)->toBe(ActionStatus::NotFound)
        ->and(Subject::query()->withoutGlobalScopes()->find($south->id)?->name)->toBe('South Physics');
});

it('re-authorizes when the confirmation is submitted', function (): void {
    actAs($this->world['admin']);
    $biology = $this->world['subjects']['biology'];
    $first = act('delete', ['record' => $biology->id]);

    // The permission is revoked between the dialog and the confirmation.
    $this->world['admin']->update(['name' => 'Former admin']);
    actAs($this->world['admin']->refresh());

    expect(act('delete', ['record' => $biology->id, 'confirmation' => $first->confirmation['token'] ?? ''])->status)->toBe(ActionStatus::Denied)
        ->and(Subject::query()->find($biology->id))->not->toBeNull();
});

it('denies actions when the tenant membership ends', function (): void {
    actAs($this->world['editor']);
    Membership::query()->where('user_id', $this->world['editor']->id)->update(['status' => 'ended']);
    app()->forgetScopedInstances();

    expect(act('create', ['input' => ['name' => 'X']])->status)->toBe(ActionStatus::Denied);
});

it('refuses forged, altered, reused, expired and transplanted confirmations', function (Closure $forge): void {
    actAs($this->world['admin']);
    $biology = $this->world['subjects']['biology'];
    $algebra = $this->world['subjects']['algebra'];
    $token = act('delete', ['record' => $biology->id])->confirmation['token'] ?? '';
    [$record, $value] = $forge($token, $biology, $algebra, $this);

    expect(act('delete', ['record' => $record, 'confirmation' => $value])->status)->toBeIn([ActionStatus::Conflict, ActionStatus::Denied, ActionStatus::NotFound])
        ->and(Subject::query()->whereKey([$biology->id, $algebra->id])->count())->toBe(2);
})->with([
    'plain true' => [fn ($token, $biology) => [$biology->id, 'true']],
    'garbage' => [fn ($token, $biology) => [$biology->id, 'abc.def']],
    'altered payload' => [fn ($token, $biology) => [$biology->id, 'x'.$token]],
    'other record' => [fn ($token, $biology, $algebra) => [$algebra->id, $token]],
    'reused' => [function ($token, $biology) {
        act('delete', ['record' => $biology->id, 'confirmation' => $token]);
        $biology->restore();

        return [$biology->id, $token];
    }],
    'expired' => [function ($token, $biology) {
        Carbon::setTestNow(now()->addHour());

        return [$biology->id, $token];
    }],
    'other user' => [function ($token, $biology) {
        $other = User::query()->create(['name' => 'Admin']);
        Membership::query()->create(['user_id' => $other->id, 'institution_id' => 1]);
        actAs($other);

        return [$biology->id, $token];
    }],
    'other action' => [fn ($token, $biology) => [$biology->id, act('force-delete', ['record' => $biology->id])->confirmation['token'] ?? 'none']],
]);

it('consumes each confirmation exactly once', function (): void {
    actAs($this->world['admin']);
    $biology = $this->world['subjects']['biology'];
    $token = act('delete', ['record' => $biology->id])->confirmation['token'] ?? '';

    expect(act('delete', ['record' => $biology->id, 'confirmation' => $token])->successful())->toBeTrue();

    $biology->restore();

    expect(act('delete', ['record' => $biology->id, 'confirmation' => $token])->status)->toBe(ActionStatus::Conflict);
});

it('signs confirmations with the application key', function (): void {
    actAs($this->world['admin']);
    $token = act('delete', ['record' => $this->world['subjects']['biology']->id])->confirmation['token'] ?? '';
    [$payload] = explode('.', $token);
    $forged = $payload.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, 'guessed-key', true)), '+/', '-_'), '=');

    expect(act('delete', ['record' => $this->world['subjects']['biology']->id, 'confirmation' => $forged])->reason)->toBe('confirmation_invalid')
        ->and(app(ConfirmationManager::class))->toBeInstanceOf(ConfirmationManager::class);
});

it('rejects undeclared fields, handler injection and unauthorized field writes', function (array $input): void {
    actAs($this->world['editor']);

    expect(act('create', ['input' => $input])->status)->toBe(ActionStatus::Denied)
        ->and(Subject::query()->where('name', 'Sneaky')->exists())->toBeFalse();
})->with([
    'tenant column' => [['name' => 'Sneaky', 'institution_id' => 2]],
    'handler class' => [['name' => 'Sneaky', 'handler' => 'App\\Evil']],
    'not writable for this user' => [['name' => 'Sneaky', 'is_published' => true]],
]);

it('allows a field write when the field permission is granted', function (): void {
    Gate::define('subjects.publish', fn ($user): bool => $user->name === 'Editor');
    actAs($this->world['editor']);

    expect(act('create', ['input' => ['name' => 'Published one', 'is_published' => true]])->successful())->toBeTrue()
        ->and(Subject::query()->where('name', 'Published one')->first()?->is_published)->toBeTrue();
});

it('denies custom actions that declare no authorization', function (): void {
    actAs($this->world['admin']);

    expect(act('ungoverned')->status)->toBe(ActionStatus::Denied)
        ->and(act('ungoverned')->reason)->toBe('no_requirement');
});

it('never executes hidden or disabled actions', function (): void {
    actAs($this->world['admin']);

    expect(act('secret')->reason)->toBe('unavailable')
        ->and(act('frozen')->reason)->toBe('unavailable');
});

it('requires a tenant for tenant-owned models', function (): void {
    $this->actingAs($this->world['editor']);
    app()->forgetScopedInstances();

    expect(act('create', ['input' => ['name' => 'No tenant']])->status)->toBe(ActionStatus::Denied);
});

it('refuses to persist Create/Edit without a validated input provider', function (): void {
    app(Registry::class)->register(new Blueprint('plain', new Ability('viewAny', Subject::class), new Source(Subject::class), new Create));
    actAs($this->world['editor']);

    $result = act('create', ['input' => ['name' => 'Unvalidated']], 'blueprint:plain');

    expect($result->status)->toBe(ActionStatus::Failed)
        ->and($result->reason)->toBe('invalid_action')
        ->and($result->message)->not->toContain('InputResolver')
        ->and(Subject::query()->where('name', 'Unvalidated')->exists())->toBeFalse();
});

it('does not leak exception details in results', function (): void {
    actAs($this->world['editor']);
    $broken = $this->world['subjects']['algebra'];
    $broken->forceFill(['code' => 'BROKEN'])->save();

    $result = act('bulk-publish', ['selection' => [$broken->id]]);

    expect($result->status)->toBe(ActionStatus::Failed)
        ->and($result->message)->not->toContain('broken')
        ->and(json_encode($result->toArray()))->not->toContain('RuntimeException');
});
