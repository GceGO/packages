<?php

declare(strict_types=1);

namespace Gcego\Settings\Persistence;

use Closure;
use Gcego\Settings\Contracts\SettingsRepository;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\Scopes\SettingScope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The Eloquent repository (gcego_settings). Writes run inside the
 * caller's transaction: rows are locked (lockForUpdate) and updated with
 * compare-and-swap on version, so two writers can never both succeed from
 * the same version; a racing insert hits the unique key and becomes a
 * conflict, not a duplicate.
 */
final readonly class EloquentSettingsRepository implements SettingsRepository
{
    public function scopes(array $instances): array
    {
        $result = [];

        if ($instances === []) {
            return $result;
        }

        $query = SettingRecord::query()->select(['id', 'key', 'scope', 'scope_key', 'value', 'type', 'encrypted', 'version'])->where(static function (Builder $query) use ($instances, &$result): void {
            foreach ($instances as $instance) {
                $result[$instance['scope']->value.'|'.$instance['key']] = [];
                $query->orWhere(static fn (Builder $query): Builder => $query->where('scope', $instance['scope']->value)->where('scope_key', $instance['key']));
            }
        });

        foreach ($query->get() as $record) {
            $result[$record->scope.'|'.$record->scope_key][$record->key] = StoredValue::fromRecord($record);
        }

        return $result;
    }

    public function find(string $key, SettingScope $scope, string $scopeKey): ?StoredValue
    {
        $record = $this->at($key, $scope, $scopeKey)->first();

        return $record instanceof SettingRecord ? StoredValue::fromRecord($record) : null;
    }

    public function lock(array $keys, SettingScope $scope, string $scopeKey): array
    {
        if ($keys === []) {
            return [];
        }

        $locked = [];

        foreach (SettingRecord::query()->where('scope', $scope->value)->where('scope_key', $scopeKey)->whereIn('key', $keys)->lockForUpdate()->get() as $record) {
            $locked[$record->key] = StoredValue::fromRecord($record);
        }

        return $locked;
    }

    public function put(string $key, SettingScope $scope, string $scopeKey, string $payload, string $type, bool $encrypted, ?int $expectedVersion, ?string $actor): StoredValue
    {
        $current = $this->at($key, $scope, $scopeKey)->lockForUpdate()->first();

        if ($expectedVersion !== null && ($current->version ?? 0) !== $expectedVersion) {
            throw SettingConflictException::for([$key]);
        }

        if (! $current instanceof SettingRecord) {
            $record = new SettingRecord;
            $record->forceFill(['key' => $key, 'scope' => $scope->value, 'scope_key' => $scopeKey, 'value' => $payload, 'type' => $type, 'encrypted' => $encrypted, 'version' => 1, 'updated_by' => $actor]);

            try {
                $record->save();
            } catch (UniqueConstraintViolationException) {
                throw SettingConflictException::for([$key]);
            }

            return StoredValue::fromRecord($record);
        }

        $updated = $this->at($key, $scope, $scopeKey)->where('version', $current->version)->toBase()->update([
            'value' => $payload, 'type' => $type, 'encrypted' => $encrypted, 'version' => $current->version + 1, 'updated_by' => $actor, 'updated_at' => $current->freshTimestampString(),
        ]);

        if ($updated !== 1) {
            throw SettingConflictException::for([$key]);
        }

        return new StoredValue($key, $scope, $scopeKey, $payload, $type, $encrypted, $current->version + 1);
    }

    public function forget(string $key, SettingScope $scope, string $scopeKey, ?int $expectedVersion): ?StoredValue
    {
        $current = $this->at($key, $scope, $scopeKey)->lockForUpdate()->first();

        if ($expectedVersion !== null && ($current->version ?? 0) !== $expectedVersion) {
            throw SettingConflictException::for([$key]);
        }

        if (! $current instanceof SettingRecord) {
            return null;
        }

        if ($this->at($key, $scope, $scopeKey)->where('version', $current->version)->toBase()->delete() !== 1) {
            throw SettingConflictException::for([$key]);
        }

        return StoredValue::fromRecord($current);
    }

    public function transaction(Closure $callback): mixed
    {
        return $this->connection()->transaction($callback);
    }

    public function afterCommit(Closure $callback): void
    {
        $connection = $this->connection();

        if (method_exists($connection, 'afterCommit')) {
            $connection->afterCommit($callback);

            return;
        }

        $callback();
    }

    public function each(Closure $callback, int $limit): int
    {
        $seen = 0;

        SettingRecord::query()->orderBy('id')->limit($limit)->get()->each(static function (SettingRecord $record) use ($callback, &$seen): void {
            $seen++;
            $callback(StoredValue::fromRecord($record));
        });

        return $seen;
    }

    /**
     * @return Builder<SettingRecord>
     */
    private function at(string $key, SettingScope $scope, string $scopeKey): Builder
    {
        return SettingRecord::query()->where('key', $key)->where('scope', $scope->value)->where('scope_key', $scopeKey);
    }

    private function connection(): ConnectionInterface
    {
        return (new SettingRecord)->getConnection();
    }
}
