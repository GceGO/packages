<?php

declare(strict_types=1);

namespace Gcego\Tables\Contracts;

use Gcego\Tables\Support\TableOwner;
use Gcego\Tables\Views\SavedView;

/**
 * Storage for saved table views. Every method is scoped to one owner
 * (guard, user key, tenant) and one table, so a view id from another user,
 * tenant or table is simply not found.
 */
interface SavedViewRepository
{
    /**
     * @return list<SavedView>
     */
    public function all(TableOwner $owner, string $table): array;

    public function find(TableOwner $owner, string $table, string $id): ?SavedView;

    /**
     * @param  array<string, mixed>  $state
     */
    public function create(TableOwner $owner, string $table, string $name, array $state, int $version): SavedView;

    /**
     * @param  array<string, mixed>|null  $state
     */
    public function update(TableOwner $owner, string $table, string $id, ?string $name = null, ?array $state = null, ?int $version = null): ?SavedView;

    public function delete(TableOwner $owner, string $table, string $id): bool;

    /**
     * Makes one view the default (or none, with null) for this owner and table.
     */
    public function setDefault(TableOwner $owner, string $table, ?string $id): void;

    public function forgetOwner(string $guard, string $key): int;

    public function count(TableOwner $owner, string $table): int;

    public function nameTaken(TableOwner $owner, string $table, string $name, ?string $except = null): bool;
}
