<?php

declare(strict_types=1);

namespace Gcego\Tables\Preferences;

use Gcego\Tables\Contracts\PreferenceStore;
use Gcego\Tables\Support\JsonState;
use Gcego\Tables\Support\TableOwner;
use Gcego\Tables\TablesConfig;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Preferences in the gcego_table_preferences table (see the package migration).
 */
final readonly class DatabasePreferenceStore implements PreferenceStore
{
    public function __construct(private ConnectionResolverInterface $connections, private TablesConfig $config) {}

    public function get(TableOwner $owner, string $table): ?array
    {
        $row = $this->scoped($owner, $table)->first(['version', 'state']);

        if ($row === null) {
            return null;
        }

        $version = get_object_vars($row)['version'] ?? null;

        return ['version' => is_numeric($version) ? (int) $version : 0, 'state' => JsonState::decode(get_object_vars($row)['state'] ?? null)];
    }

    public function put(TableOwner $owner, string $table, array $state, int $version): void
    {
        $now = Date::now();

        $this->query()->upsert(
            [[
                'table_identity' => $table,
                'guard' => $owner->guard,
                'owner_key' => $owner->key,
                'tenant_key' => $owner->tenant,
                'version' => $version,
                'state' => JsonState::encode($state),
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['table_identity', 'guard', 'owner_key', 'tenant_key'],
            ['version', 'state', 'updated_at'],
        );
    }

    public function forget(TableOwner $owner, string $table): void
    {
        $this->scoped($owner, $table)->delete();
    }

    public function forgetOwner(string $guard, string $key): int
    {
        return $this->query()->where('guard', $guard)->where('owner_key', $key)->delete();
    }

    private function scoped(TableOwner $owner, string $table): Builder
    {
        return $this->query()
            ->where('table_identity', $table)
            ->where('guard', $owner->guard)
            ->where('owner_key', $owner->key)
            ->where('tenant_key', $owner->tenant);
    }

    private function query(): Builder
    {
        return $this->connections->connection($this->config->connection)->table($this->config->preferencesTable);
    }
}
