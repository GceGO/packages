<?php

declare(strict_types=1);

use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;

it('registers panel, module, resource, field and configured permissions without granting any', function (): void {
    $result = vault()->sync();

    expect(Permission::query()->pluck('name')->all())->toContain(
        'panels.desk.access', 'modules.library.access', 'books.viewAny', 'books.forceDelete', 'books.isbn.view', 'books.isbn.update', 'reports.export', 'vault.roles.assign',
    )->and($result['permissions']['created'])->not->toBeEmpty()
        ->and(Grant::query()->count())->toBe(0)
        ->and(Gcego\Vault\Models\Assignment::query()->count())->toBe(0);
});

it('records ability dependencies and keeps names unique regardless of case', function (): void {
    vault()->sync();

    expect(vault()->permissions()->require('books.update')->dependencies())->toBe(['books.view'])
        ->and(vault()->permissions()->find('BOOKS.UPDATE')?->name)->toBe('books.update');

    expect(fn () => vault()->permissions()->create(administrator(), ['name' => 'Books.Update']))->toThrow(Gcego\Vault\Exceptions\VaultValidationException::class);
});

it('marks permissions that are no longer declared as stale, and prunes only unused ones', function (): void {
    vault()->sync();
    $gone = new Permission;
    $gone->forceFill(['name' => 'old.thing', 'normalized' => 'old.thing', 'label' => 'Old', 'source' => 'discovered'])->save();

    expect(vault()->sync()['permissions']['stale'])->toBe(['old.thing'])
        ->and(vault()->sync(prune: true)['permissions']['pruned'])->toBe(['old.thing'])
        ->and(vault()->permissions()->find('old.thing'))->toBeNull();
});

it('is idempotent', function (): void {
    vault()->sync();

    $second = vault()->sync();

    expect($second['permissions']['created'])->toBe([])->and($second['permissions']['updated'])->toBe([]);
});
