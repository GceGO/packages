<?php

declare(strict_types=1);

use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\PermissionProvider;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Permissions\Permissions;
use Gcego\Security\Permissions\SpatiePermissionProvider;
use Gcego\Security\Roles\RoleResolver;
use Gcego\Security\Roles\SpatieRoleProvider;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->world = world();
});

it('resolves global permissions through Gate abilities', function (): void {
    Gate::define('subjects.view', fn (User $user): bool => $user->name === 'Teacher');
    $this->actingAs($this->world['teacher']);

    expect(security()->permission('subjects.view')->allowed)->toBeTrue()
        ->and(security()->permission('subjects.update')->reason)->toBe(DenialReason::MissingPermission)
        ->and(security()->check(new Permissions('subjects.view', 'subjects.update'))->allowed)->toBeFalse()
        ->and(security()->check(Permissions::any('subjects.update', 'subjects.view'))->allowed)->toBeTrue()
        ->and(security()->check(Permissions::any('subjects.update', 'subjects.delete'))->reason)->toBe(DenialReason::MissingPermission);
});

it('rejects invalid permission identifiers before checking anything', function (): void {
    $this->actingAs($this->world['teacher']);

    security()->permission('subjects view');
})->throws(InvalidPermissionException::class);

it('integrates spatie/laravel-permission through its Gate integration and directly', function (): void {
    SpatiePermission::create(['name' => 'subjects.view', 'guard_name' => 'web']);
    SpatiePermission::create(['name' => 'subjects.update', 'guard_name' => 'web']);
    SpatieRole::create(['name' => 'curriculum-editor', 'guard_name' => 'web'])->givePermissionTo('subjects.update');
    $this->world['teacher']->givePermissionTo('subjects.view');
    $this->world['admin']->assignRole('curriculum-editor');

    $this->actingAs($this->world['teacher']);
    expect(security()->permission('subjects.view')->allowed)->toBeTrue()
        ->and(security()->permission('subjects.update')->allowed)->toBeFalse();

    $this->rebuildApplication(['gcego-security.permissions.provider' => 'spatie']);
    SpatiePermission::create(['name' => 'subjects.view', 'guard_name' => 'web']);
    $teacher = User::query()->create(['name' => 'Spatie teacher']);
    $teacher->givePermissionTo('subjects.view');
    $this->actingAs($teacher);

    expect(app(PermissionProvider::class))->toBeInstanceOf(SpatiePermissionProvider::class)
        ->and(security()->permission('subjects.view')->allowed)->toBeTrue()
        ->and(security()->permission('subjects.unknown')->reason)->toBe(DenialReason::UnknownPermission);
});

it('reflects permission changes in the next request, not a stale cache', function (): void {
    SpatiePermission::create(['name' => 'subjects.view', 'guard_name' => 'web']);
    $this->actingAs($this->world['teacher']);

    expect(security()->permission('subjects.view')->allowed)->toBeFalse();

    $this->world['teacher']->givePermissionTo('subjects.view');

    // Same request: decisions are memoized per request.
    expect(security()->permission('subjects.view')->allowed)->toBeFalse();

    // Or explicitly, within the same request.
    security()->forgetDecisions();
    $this->world['teacher']->refresh();

    expect(security()->permission('subjects.view')->allowed)->toBeTrue();

    $this->world['teacher']->revokePermissionTo('subjects.view');
    newRequest();
    $this->world['teacher']->refresh();

    expect(security()->permission('subjects.view')->allowed)->toBeFalse();
});

it('grants tenant permissions only from the membership of the active tenant', function (): void {
    $teacher = $this->world['teacher'];
    $this->actingAs($teacher);

    expect(security()->permission('grades.update', tenant: true)->reason)->toBe(DenialReason::TenantRequired);

    security()->enterTenant((string) $this->world['north']->id);

    expect(security()->permission('grades.update', tenant: true)->allowed)->toBeTrue()
        ->and(security()->permission('members.invite', tenant: true)->reason)->toBe(DenialReason::MissingPermission)
        ->and(security()->check(Permission::inTenant('subjects.view'))->allowed)->toBeTrue();
});

it('evaluates the same person differently in each institution', function (): void {
    $admin = $this->world['admin'];
    $this->actingAs($admin);

    security()->enterTenant((string) $this->world['north']->id);
    expect(security()->permission('members.invite', tenant: true)->allowed)->toBeTrue()
        ->and(security()->roles()->tenant)->toBe(['administrator']);

    security()->enterTenant((string) $this->world['south']->id);
    expect(security()->permission('members.invite', tenant: true)->allowed)->toBeFalse()
        ->and(security()->permission('grades.update', tenant: true)->allowed)->toBeTrue()
        ->and(security()->roles()->tenant)->toBe(['teacher'])
        ->and(security()->roles()->tenantId)->toBe('institution:'.$this->world['south']->id);
});

it('applies membership role changes on the next request', function (): void {
    $this->actingAs($this->world['teacher']);
    security()->enterTenant((string) $this->world['north']->id);

    expect(security()->permission('members.invite', tenant: true)->allowed)->toBeFalse();

    Membership::query()->where('user_id', $this->world['teacher']->id)->where('institution_id', $this->world['north']->id)->update(['role' => 'administrator']);
    newRequest();
    security()->enterTenant((string) $this->world['north']->id);

    expect(security()->permission('members.invite', tenant: true)->allowed)->toBeTrue();
});

it('keeps global and tenant roles apart and never authorizes by role name', function (): void {
    SpatieRole::create(['name' => 'platform-admin', 'guard_name' => 'web']);
    $this->world['teacher']->assignRole('platform-admin');
    $this->actingAs($this->world['teacher']);
    $roles = (new RoleResolver(new SpatieRoleProvider))->roles(security()->context());

    expect($roles->global)->toBe(['platform-admin'])
        ->and($roles->tenant)->toBe([])
        ->and($roles->hasGlobal('platform-admin'))->toBeTrue()
        ->and(security()->permission('members.invite', tenant: true)->allowed)->toBeFalse()
        ->and(security()->authorize('update', $this->world['subjects']['draft'])->allowed)->toBeFalse();
});
