<?php

declare(strict_types=1);

use Gcego\Vault\Exceptions\CircularInheritanceException;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Models\Role;

beforeEach(fn () => synced());

it('creates roles with permissions and inheritance, validating names and dependencies', function (): void {
    $admin = administrator(also: ['books.viewAny', 'books.view', 'books.update']);
    vault()->roles()->create($admin, ['name' => 'viewer', 'label' => 'Viewer', 'permissions' => ['books.viewAny', 'books.view']]);
    $editor = vault()->roles()->create($admin, ['name' => 'editor', 'label' => 'Editor', 'permissions' => ['books.update'], 'inherits' => ['viewer']]);

    expect($editor->parents()->pluck('name')->all())->toBe(['viewer'])
        ->and(fn () => vault()->roles()->create($admin, ['name' => 'Bad Name', 'label' => 'x']))->toThrow(VaultValidationException::class)
        ->and(fn () => vault()->roles()->create($admin, ['name' => 'viewer', 'label' => 'Again']))->toThrow(VaultValidationException::class)
        ->and(fn () => vault()->roles()->create($admin, ['name' => 'lonely', 'label' => 'Lonely', 'permissions' => ['books.update']]))->toThrow(VaultValidationException::class, 'books.view');
});

it('refuses roles granting more than their creator holds', function (): void {
    $admin = administrator();

    expect(fn () => vault()->roles()->create($admin, ['name' => 'deleter', 'label' => 'D', 'permissions' => ['books.viewAny']]))->toThrow(VaultAuthorizationException::class)
        ->and(Role::query()->where('name', 'deleter')->exists())->toBeFalse();
});

it('refuses circular inheritance', function (): void {
    $admin = administrator();
    $a = vault()->roles()->create($admin, ['name' => 'a', 'label' => 'A']);
    $b = vault()->roles()->create($admin, ['name' => 'b', 'label' => 'B', 'inherits' => ['a']]);
    vault()->roles()->create($admin, ['name' => 'c', 'label' => 'C', 'inherits' => ['b']]);

    expect(fn () => vault()->roles()->syncInheritance($admin, $a, ['c']))->toThrow(CircularInheritanceException::class)
        ->and(fn () => vault()->roles()->syncInheritance($admin, $a, ['a']))->toThrow(CircularInheritanceException::class)
        ->and($a->parents()->count())->toBe(0);
});

it('archives, restores and deletes custom roles, but never system or protected ones', function (): void {
    $this->reboot(['gcego-vault.roles' => ['owner' => ['permissions' => ['books.viewAny'], 'protected' => true], 'staff' => ['permissions' => ['books.viewAny']]]]);
    synced();
    $admin = administrator(also: ['books.viewAny']);
    $temp = vault()->roles()->create($admin, ['name' => 'temp', 'label' => 'Temp', 'permissions' => ['books.viewAny']]);
    $holder = person('Holder');
    $holder->assignRole('temp');

    vault()->roles()->archive($admin, $temp);
    expect($holder->hasPermissionTo('books.viewAny'))->toBeFalse();
    vault()->roles()->restore($admin, $temp);
    expect($holder->hasPermissionTo('books.viewAny'))->toBeTrue()
        ->and(fn () => vault()->roles()->delete($admin, $temp))->toThrow(VaultValidationException::class);

    $holder->removeRole('temp');
    vault()->roles()->delete($admin, $temp);

    expect(Role::query()->where('name', 'temp')->exists())->toBeFalse()
        ->and(fn () => vault()->roles()->delete($admin, vault()->roles()->require('staff')))->toThrow(VaultAuthorizationException::class)
        ->and(fn () => vault()->roles()->archive($admin, vault()->roles()->require('owner')))->toThrow(VaultAuthorizationException::class)
        ->and(fn () => vault()->roles()->update($admin, vault()->roles()->require('staff'), ['name' => 'renamed']))->toThrow(VaultAuthorizationException::class);
});

it('creates configured system roles once and respects their sync mode', function (): void {
    $this->reboot(['gcego-vault.roles' => [
        'reader' => ['permissions' => ['books.view*']],
        'locked' => ['permissions' => ['books.viewAny'], 'sync' => 'replace'],
    ]]);
    synced();
    $admin = administrator(also: ['books.viewAny', 'books.view', 'books.delete']);
    $reader = vault()->roles()->require('reader');

    expect($reader->permissions()->pluck('name')->sort()->values()->all())->toBe(['books.view', 'books.viewAny'])
        ->and($reader->is_system)->toBeTrue();

    // "create": administrators own the permissions afterwards, and sync leaves their change alone.
    vault()->roles()->syncPermissions($admin, $reader, ['books.viewAny']);
    synced();
    expect($reader->permissions()->pluck('name')->all())->toBe(['books.viewAny'])
        ->and(fn () => vault()->roles()->syncPermissions($admin, vault()->roles()->require('locked'), ['books.viewAny', 'books.view']))->toThrow(VaultAuthorizationException::class);
});

it('duplicates, exports and imports role definitions', function (): void {
    $admin = administrator(also: ['books.viewAny', 'books.view']);
    $viewer = vault()->roles()->create($admin, ['name' => 'viewer', 'label' => 'Viewer', 'permissions' => ['books.viewAny', 'books.view']]);
    $copy = vault()->roles()->duplicate($admin, $viewer, 'viewer-two');
    $export = vault()->roles()->export($admin, ['viewer', 'viewer-two']);

    vault()->roles()->delete($admin, $copy);
    $result = vault()->roles()->import($admin, $export);

    expect($export['roles'])->toHaveCount(2)
        ->and($result)->toBe(['created' => ['viewer-two'], 'updated' => [], 'skipped' => ['viewer']])
        ->and(vault()->roles()->require('viewer-two')->permissions()->count())->toBe(2)
        ->and(fn () => vault()->roles()->import($admin, ['format' => 'other']))->toThrow(VaultValidationException::class);
});

it('rejects an import granting more than the importer holds, all or nothing', function (): void {
    $admin = administrator(also: ['books.viewAny']);
    $payload = ['format' => 'gcego-vault-roles', 'version' => 1, 'roles' => [
        ['name' => 'fine', 'label' => 'Fine', 'permissions' => ['books.viewAny']],
        ['name' => 'greedy', 'label' => 'Greedy', 'permissions' => ['books.viewAny', 'books.view', 'books.delete']],
    ]];

    expect(fn () => vault()->roles()->import($admin, $payload))->toThrow(VaultAuthorizationException::class)
        ->and(Role::query()->whereIn('name', ['fine', 'greedy'])->count())->toBe(0);
});
