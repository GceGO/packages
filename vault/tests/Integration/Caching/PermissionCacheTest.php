<?php

declare(strict_types=1);

use Gcego\Vault\Caching\PermissionCache;
use Gcego\Vault\Models\Assignment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

beforeEach(fn () => synced());

it('serves later requests from the cache without querying assignments', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Cached');
    $user->assignRole('viewer');
    vault()->effective($user);
    app()->forgetScopedInstances();

    $queries = queriesDuring(fn () => expect($user->hasPermissionTo('books.viewAny'))->toBeTrue());

    expect($queries)->toBe([]);
});

it('takes a revocation into account on the very next check, in other requests too', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Revoked');
    $user->assignRole('viewer');
    expect($user->hasPermissionTo('books.viewAny'))->toBeTrue();

    $user->removeRole('viewer');
    expect($user->hasPermissionTo('books.viewAny'))->toBeFalse();

    app()->forgetScopedInstances();
    expect($user->hasPermissionTo('books.viewAny'))->toBeFalse();
});

it('takes a role permission change into account for every holder at once', function (): void {
    $viewer = role('viewer', ['books.viewAny']);
    [$first, $second] = [person('One'), person('Two')];
    $first->assignRole('viewer');
    $second->assignRole('viewer');
    expect($first->hasPermissionTo('books.view'))->toBeFalse();

    vault()->roles()->syncPermissions(administrator(also: ['books.viewAny', 'books.view']), $viewer, ['books.viewAny', 'books.view']);
    app()->forgetScopedInstances();

    expect($first->hasPermissionTo('books.view'))->toBeTrue()->and($second->hasPermissionTo('books.view'))->toBeTrue();
});

it('never reuses an entry after its version token was evicted from the cache', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Evicted');
    $user->assignRole('viewer');
    expect($user->hasPermissionTo('books.viewAny'))->toBeTrue();

    // The holder's version token disappears (eviction), then the assignment is revoked without Vault's knowledge.
    Cache::forget(app(PermissionCache::class)->key('v', 'h', sha1($user->getMorphClass().'|'.$user->id)));
    Assignment::query()->update(['revoked_at' => Date::now(), 'active_key' => null]);
    app()->forgetScopedInstances();

    expect($user->hasPermissionTo('books.viewAny'))->toBeFalse();
});

it('does not cache temporary access beyond its expiry', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Short');
    $user->assignRole('viewer', until: Date::now()->addMinutes(5));
    expect($user->hasPermissionTo('books.viewAny'))->toBeTrue();

    $this->travel(6)->minutes();
    app()->forgetScopedInstances();

    expect($user->hasPermissionTo('books.viewAny'))->toBeFalse();
});

it('keeps working, uncached, when caching is disabled', function (): void {
    $this->reboot(['gcego-vault.cache.enabled' => false]);
    synced();
    role('viewer', ['books.viewAny']);
    $user = person('Uncached');
    $user->assignRole('viewer');
    app()->forgetScopedInstances();

    expect($user->hasPermissionTo('books.viewAny'))->toBeTrue();
});
