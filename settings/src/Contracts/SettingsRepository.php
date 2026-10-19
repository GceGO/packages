<?php

declare(strict_types=1);

namespace Gcego\Settings\Contracts;

use Closure;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\Persistence\StoredValue;
use Gcego\Settings\Scopes\SettingScope;

/**
 * Storage of setting overrides. Keys and scopes come from registered
 * definitions and server-side scope contexts only; implementations never
 * accept SQL fragments or column names.
 *
 * Expected versions: null skips the check, 0 expects no stored row, and a
 * positive number expects exactly that version (else SettingConflictException).
 */
interface SettingsRepository
{
    /**
     * Every stored value of several scope instances, in one query.
     *
     * @param  list<array{scope: SettingScope, key: string}>  $instances
     * @return array<string, array<string, StoredValue>> "scope|scope key" => setting key => value
     */
    public function scopes(array $instances): array;

    public function find(string $key, SettingScope $scope, string $scopeKey): ?StoredValue;

    /**
     * Lock the rows of these keys at one scope instance for the current transaction.
     *
     * @param  list<string>  $keys
     * @return array<string, StoredValue>
     */
    public function lock(array $keys, SettingScope $scope, string $scopeKey): array;

    /**
     * Insert or update one row; the stored version grows by one.
     *
     * @throws SettingConflictException
     */
    public function put(string $key, SettingScope $scope, string $scopeKey, string $payload, string $type, bool $encrypted, ?int $expectedVersion, ?string $actor): StoredValue;

    /**
     * Delete one row (reset to the inherited value); returns what was deleted.
     *
     * @throws SettingConflictException
     */
    public function forget(string $key, SettingScope $scope, string $scopeKey, ?int $expectedVersion): ?StoredValue;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed;

    /**
     * Run once the current transaction commits (now, outside one); dropped on rollback.
     *
     * @param  Closure(): void  $callback
     */
    public function afterCommit(Closure $callback): void;

    /**
     * Visit stored rows in bounded chunks (diagnostics).
     *
     * @param  Closure(StoredValue): void  $callback
     */
    public function each(Closure $callback, int $limit): int;
}
