<?php

declare(strict_types=1);

namespace Gcego\Tables\Contracts;

use Gcego\Tables\Support\TableOwner;

/**
 * Per-user, per-table (and per-tenant) column preferences. Stored state is
 * untrusted on the way back: TableManager validates it against the current
 * table and the user's permissions before using it.
 */
interface PreferenceStore
{
    /**
     * @return array{version: int, state: array<string, mixed>}|null
     */
    public function get(TableOwner $owner, string $table): ?array;

    /**
     * @param  array<string, mixed>  $state
     */
    public function put(TableOwner $owner, string $table, array $state, int $version): void;

    public function forget(TableOwner $owner, string $table): void;

    /**
     * Removes everything stored for a user (call it when deleting accounts).
     */
    public function forgetOwner(string $guard, string $key): int;
}
