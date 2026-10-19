<?php

declare(strict_types=1);

namespace Gcego\Tables\Views;

use Gcego\Tables\Contracts\SavedViewRepository;
use Gcego\Tables\Support\JsonState;
use Gcego\Tables\Support\TableOwner;
use Gcego\Tables\TablesConfig;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Saved views in the gcego_table_views table. Every query is constrained to
 * the owner (guard, user key, tenant) and table, so ids are only meaningful
 * to their owner.
 */
final readonly class DatabaseSavedViewRepository implements SavedViewRepository
{
    public function __construct(private ConnectionResolverInterface $connections, private TablesConfig $config) {}

    public function all(TableOwner $owner, string $table): array
    {
        return array_values(array_map($this->view(...), $this->scoped($owner, $table)->orderBy('name')->limit($this->config->maxSavedViews)->get()->all()));
    }

    public function find(TableOwner $owner, string $table, string $id): ?SavedView
    {
        if (! Str::isUlid($id)) {
            return null;
        }

        $row = $this->scoped($owner, $table)->where('id', strtolower($id))->first();

        return $row === null ? null : $this->view($row);
    }

    public function create(TableOwner $owner, string $table, string $name, array $state, int $version): SavedView
    {
        $id = strtolower((string) Str::ulid());
        $now = Date::now();

        $this->query()->insert([
            'id' => $id,
            'table_identity' => $table,
            'guard' => $owner->guard,
            'owner_key' => $owner->key,
            'tenant_key' => $owner->tenant,
            'name' => $name,
            'version' => $version,
            'state' => JsonState::encode($state),
            'is_default' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new SavedView($id, $name, $state, $version);
    }

    public function update(TableOwner $owner, string $table, string $id, ?string $name = null, ?array $state = null, ?int $version = null): ?SavedView
    {
        if ($this->find($owner, $table, $id) === null) {
            return null;
        }

        $this->scoped($owner, $table)->where('id', strtolower($id))->update(array_filter([
            'name' => $name,
            'state' => $state === null ? null : JsonState::encode($state),
            'version' => $version,
            'updated_at' => Date::now(),
        ], static fn (mixed $value): bool => $value !== null));

        return $this->find($owner, $table, $id);
    }

    public function delete(TableOwner $owner, string $table, string $id): bool
    {
        return Str::isUlid($id) && $this->scoped($owner, $table)->where('id', strtolower($id))->delete() > 0;
    }

    public function setDefault(TableOwner $owner, string $table, ?string $id): void
    {
        $this->connections->connection($this->config->connection)->transaction(function () use ($owner, $table, $id): void {
            $this->scoped($owner, $table)->where('is_default', true)->update(['is_default' => false]);

            if ($id !== null && Str::isUlid($id)) {
                $this->scoped($owner, $table)->where('id', strtolower($id))->update(['is_default' => true]);
            }
        });
    }

    public function forgetOwner(string $guard, string $key): int
    {
        return $this->query()->where('guard', $guard)->where('owner_key', $key)->delete();
    }

    public function count(TableOwner $owner, string $table): int
    {
        return $this->scoped($owner, $table)->count();
    }

    public function nameTaken(TableOwner $owner, string $table, string $name, ?string $except = null): bool
    {
        $query = $this->scoped($owner, $table)->where('name', $name);

        if ($except !== null) {
            $query->where('id', '!=', strtolower($except));
        }

        return $query->exists();
    }

    private function view(object $row): SavedView
    {
        $data = get_object_vars($row);
        $version = $data['version'] ?? null;

        return new SavedView(
            is_string($data['id'] ?? null) ? $data['id'] : '',
            is_string($data['name'] ?? null) ? $data['name'] : '',
            JsonState::decode($data['state'] ?? null),
            is_numeric($version) ? (int) $version : 0,
            (bool) ($data['is_default'] ?? false),
        );
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
        return $this->connections->connection($this->config->connection)->table($this->config->viewsTable);
    }
}
