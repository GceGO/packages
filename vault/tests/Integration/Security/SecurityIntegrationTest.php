<?php

declare(strict_types=1);

use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\SecurityManager;
use Gcego\Vault\Tests\Fixtures\Models\Book;
use Illuminate\Support\Facades\Gate;

beforeEach(fn () => synced());

it('answers gcego/security permission requirements from Vault', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Viewer');
    $user->assignRole('viewer');
    actAs($user);

    expect(app(SecurityManager::class)->check(new Permission('books.viewAny'))->allowed)->toBeTrue()
        ->and(app(SecurityManager::class)->check(new Permission('books.delete'))->allowed)->toBeFalse()
        ->and(app(SecurityManager::class)->check(new Permission('books.delete'))->reason->value)->toBe('missing_permission');
});

it('passes permissions Vault does not know to the provider configured before it', function (): void {
    actAs(person('Legacy'));
    expect(app(SecurityManager::class)->check(new Permission('legacy.ability'))->allowed)->toBeTrue();

    actAs(person('Other'));
    expect(app(SecurityManager::class)->check(new Permission('legacy.ability'))->allowed)->toBeFalse()
        ->and(app(SecurityManager::class)->check(new Permission('never.defined'))->allowed)->toBeFalse();
});

it('lets the Gate answer Vault permission names', function (): void {
    role('exporter', ['reports.export']);
    $user = person('Exporter');
    $user->assignRole('exporter');

    expect(Gate::forUser($user)->allows('reports.export'))->toBeTrue()
        ->and($user->can('reports.export'))->toBeTrue()
        ->and(Gate::forUser(person('Nobody'))->allows('reports.export'))->toBeFalse();
});

it('never lets a generic permission override a policy decision', function (): void {
    role('editor', ['books.viewAny', 'books.view', 'books.update']);
    $editor = person('Editor');
    $editor->assignRole('editor');
    $own = Book::query()->create(['title' => 'Mine', 'owner_id' => $editor->id]);
    $other = Book::query()->create(['title' => 'Theirs', 'owner_id' => 999]);

    expect(Gate::forUser($editor)->allows('update', $own))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('update', $other))->toBeFalse();
});

it('adds tenant roles to verified memberships only, and only in their tenant', function (): void {
    role('teacher', ['grades.update']);
    $teacher = person('Teacher', [1]);
    $teacher->assignRole('teacher', 'institution:1');
    // An assignment in a tenant the person is not a member of opens nothing.
    $teacher->assignRole('teacher', 'institution:2');

    actAs($teacher, 1);
    expect(app(SecurityManager::class)->check(Permission::inTenant('grades.update'))->allowed)->toBeTrue()
        ->and(app(SecurityManager::class)->roles()->tenant)->toContain('teacher')
        ->and(app(SecurityManager::class)->check(new Permission('grades.update'))->allowed)->toBeFalse();

    actAs($teacher);
    expect(fn () => app(SecurityManager::class)->enterTenant('2'))->toThrow(TenantAccessException::class);
});

it('reports global Vault roles to gcego/security', function (): void {
    role('viewer', []);
    role('editor', [], inherits: ['viewer']);
    $user = person('Roles');
    $user->assignRole('editor');
    actAs($user);

    expect(app(SecurityManager::class)->roles()->global)->toBe(['editor', 'viewer']);
});
