<?php

declare(strict_types=1);

use Gcego\Vault\Authorization\Explanation;
use Illuminate\Support\Facades\Date;

beforeEach(fn () => synced());

it('denies everything to a person without roles, and does not know unregistered permissions', function (): void {
    $nobody = person('Nobody');

    expect(vault()->allows($nobody, 'books.view'))->toBeFalse()
        ->and(vault()->evaluator()->allows($nobody, 'books.view'))->toBeFalse()
        ->and(vault()->evaluator()->allows($nobody, 'nothing.registered'))->toBeNull()
        ->and(vault()->explain($nobody, 'nothing.registered')->reason)->toBe(Explanation::UnknownPermission);
});

it('grants the union of held roles, inherited roles and direct grants, with their sources', function (): void {
    role('viewer', ['books.viewAny', 'books.view']);
    role('editor', ['books.update'], inherits: ['viewer']);
    $editor = person('Ed');
    $editor->assignRole('editor');
    $editor->givePermissionTo('reports.export');

    $effective = vault()->effective($editor);

    expect($effective->roles)->toBe(['editor'])
        ->and($effective->inherited)->toBe(['viewer'])
        ->and($effective->names())->toBe(['books.update', 'books.view', 'books.viewAny', 'reports.export'])
        ->and($effective->sources('books.view'))->toBe(['role:viewer (via editor)'])
        ->and($effective->sources('reports.export'))->toBe(['direct'])
        ->and($editor->hasPermissionTo('books.delete'))->toBeFalse();
});

it('follows inheritance through several levels and survives a cycle that reached the database', function (): void {
    role('viewer', ['books.viewAny']);
    role('editor', ['books.view'], inherits: ['viewer']);
    $manager = role('manager', ['books.update'], inherits: ['editor']);
    // A cycle written behind RoleManager's back: viewer -> manager.
    $manager->getConnection()->table('gcego_vault_role_inheritance')->insert(['role_id' => Gcego\Vault\Models\Role::query()->where('name', 'viewer')->value('id'), 'inherits_id' => $manager->id]);
    app(Gcego\Vault\Caching\Invalidation::class)->definitions();
    $user = person('Mo');
    $user->assignRole('viewer');

    expect(vault()->effective($user)->names())->toBe(['books.update', 'books.view', 'books.viewAny']);
});

it('stops counting temporary access the moment it expires, before any cleanup', function (): void {
    role('editor', ['books.viewAny']);
    $user = person('Temp');
    $user->assignRole('editor', until: Date::now()->addHour());

    expect($user->hasPermissionTo('books.viewAny'))->toBeTrue();

    $this->travel(61)->minutes();
    app()->forgetScopedInstances();

    expect($user->hasPermissionTo('books.viewAny'))->toBeFalse();
});

it('keeps global and tenant grants apart', function (): void {
    role('teacher', ['books.viewAny', 'grades.update']);
    $user = person('Tess', [1, 2]);
    $user->assignRole('teacher', 'institution:1');

    expect($user->hasPermissionTo('books.viewAny'))->toBeFalse()
        ->and($user->hasPermissionTo('books.viewAny', 'institution:1'))->toBeTrue()
        ->and($user->hasPermissionTo('grades.update', 'institution:1'))->toBeTrue()
        ->and($user->hasPermissionTo('books.viewAny', 'institution:2'))->toBeFalse();
});

it('never grants a tenant-only permission globally, nor a global-only one in a tenant', function (): void {
    role('mixed', ['grades.update', 'reports.export']);
    $user = person('Mix', [1]);
    $user->assignRole('mixed');
    $user->assignRole('mixed', 'institution:1');

    expect($user->hasPermissionTo('grades.update'))->toBeFalse()
        ->and(vault()->explain($user, 'grades.update')->reason)->toBe(Explanation::WrongScope)
        ->and($user->hasPermissionTo('reports.export'))->toBeTrue()
        ->and($user->hasPermissionTo('reports.export', 'institution:1'))->toBeFalse()
        ->and($user->hasPermissionTo('grades.update', 'institution:1'))->toBeTrue();
});

it('ignores archived roles and the roles reached only through them', function (): void {
    role('viewer', ['books.viewAny']);
    $editor = role('editor', ['books.update'], inherits: ['viewer']);
    $user = person('Arch');
    $user->assignRole('editor');
    $editor->forceFill(['archived_at' => Date::now()])->save();
    app(Gcego\Vault\Caching\Invalidation::class)->definitions();

    expect(vault()->effective($user)->names())->toBe([]);
});

it('applies a panel-limited role only inside its panel', function (): void {
    $role = role('desk-clerk', ['books.viewAny']);
    $role->forceFill(['panel' => 'desk'])->save();
    app(Gcego\Vault\Caching\Invalidation::class)->definitions();
    $user = person('Clerk');
    $user->assignRole('desk-clerk');

    expect(vault()->evaluator()->allows($user, 'books.viewAny', '', 'desk'))->toBeTrue()
        ->and(vault()->evaluator()->allows($user, 'books.viewAny', '', 'other'))->toBeFalse()
        ->and(vault()->evaluator()->allows($user, 'books.viewAny'))->toBeFalse();
});

it('gives super administrators every registered global permission except the excluded ones, and nothing in tenants', function (): void {
    $this->reboot(['gcego-vault.super_admin' => ['enabled' => true, 'excluded' => ['reports.*']]]);
    synced();
    $root = role('root');
    $root->forceFill(['is_super' => true])->save();
    app(Gcego\Vault\Caching\Invalidation::class)->definitions();
    $user = person('Root', [1]);
    $user->assignRole('root');

    expect($user->hasPermissionTo('books.delete'))->toBeTrue()
        ->and(vault()->explain($user, 'books.delete')->reason)->toBe(Explanation::SuperAdministrator)
        ->and($user->hasPermissionTo('reports.export'))->toBeFalse()
        ->and(vault()->explain($user, 'reports.export')->reason)->toBe(Explanation::SuperExcluded)
        ->and($user->hasPermissionTo('books.delete', 'institution:1'))->toBeFalse()
        ->and($user->hasPermissionTo('nothing.registered'))->toBeFalse();
});

it('does not treat super roles as super while super administrators are disabled', function (): void {
    $root = role('root');
    $root->forceFill(['is_super' => true])->save();
    app(Gcego\Vault\Caching\Invalidation::class)->definitions();
    $user = person('Root');
    $user->assignRole('root');

    expect($user->hasPermissionTo('books.delete'))->toBeFalse();
});
