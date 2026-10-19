<?php

declare(strict_types=1);

namespace Gcego\Settings\Cache;

use Gcego\Settings\Contracts\SettingsRepository;
use Gcego\Settings\Persistence\StoredValue;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\SettingsConfig;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Stored values per scope instance, through Laravel's cache, with
 * per-request memory.
 *
 * Consistency across workers: every scope instance has a stamp in the
 * cache. Readers read the stamp first, then the entry for that stamp (or
 * the database, then store it under that stamp). Writers change the stamp
 * after their transaction commits. A reader that loaded old rows can only
 * have stored them under the old stamp, which nobody reads any more, so a
 * stale value is never served after the write is visible.
 *
 * What is cached: the stored payloads, exactly as in the database, so
 * sensitive values stay encrypted in the cache. Request memory lives in
 * this scoped instance (forgotten after every request and queued job), never
 * in static state, so nothing leaks between tenants or users in Octane or
 * queue workers. Cache failures fall back to the database.
 */
final class SettingsCache
{
    /** @var array<string, array<string, StoredValue>> "scope|key" => setting key => value */
    private array $memory = [];

    private ?string $generation = null;

    public int $hits = 0;

    public int $misses = 0;

    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly CacheFactory $cache,
        private readonly SettingsConfig $config,
        private readonly SettingsCacheKeyResolver $keys,
    ) {}

    /**
     * Stored values of several scope instances (cached, else one database query for all misses).
     *
     * @param  list<array{scope: SettingScope, key: string}>  $instances
     * @return array<string, array<string, StoredValue>>
     */
    public function scopes(array $instances): array
    {
        $result = [];
        $missing = [];

        foreach ($instances as $instance) {
            $id = $instance['scope']->value.'|'.$instance['key'];

            if (isset($this->memory[$id])) {
                $result[$id] = $this->memory[$id];
            } else {
                $missing[$id] = $instance;
            }
        }

        if ($missing === []) {
            return $result;
        }

        $store = $this->store();
        $stamps = [];

        if ($store !== null) {
            try {
                $generation = $this->generation($store);
                $stampKeys = [];

                foreach ($missing as $id => $instance) {
                    $stampKeys[$id] = $this->keys->stamp($generation, $instance['scope'], $instance['key']);
                }

                $stampValues = $store->many(array_values($stampKeys));
                $entryKeys = [];

                foreach ($stampKeys as $id => $stampKey) {
                    $stamp = $stampValues[$stampKey] ?? null;
                    $stamps[$id] = is_string($stamp) ? $stamp : '0';
                    $entryKeys[$id] = $this->keys->scope($generation, $missing[$id]['scope'], $missing[$id]['key'], $stamps[$id]);
                }

                $entries = $store->many(array_values($entryKeys));

                foreach ($entryKeys as $id => $entryKey) {
                    $entry = $entries[$entryKey] ?? null;

                    if (is_array($entry)) {
                        $this->hits++;
                        $result[$id] = $this->memory[$id] = $this->fromCache($missing[$id], $entry);
                        unset($missing[$id]);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
                $stamps = [];
            }
        }

        if ($missing === []) {
            return $result;
        }

        $this->misses += count($missing);
        $loaded = $this->repository->scopes(array_values($missing));

        foreach ($missing as $id => $instance) {
            $values = $loaded[$id] ?? [];
            $result[$id] = $this->memory[$id] = $values;

            if ($store !== null && isset($stamps[$id])) {
                try {
                    $store->put($this->keys->scope($this->generation($store), $instance['scope'], $instance['key'], $stamps[$id]), array_map(static fn (StoredValue $value): array => $value->toCache(), $values), $this->config->cacheTtl);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $result;
    }

    /**
     * Invalidate one scope instance everywhere (call after the write commits).
     */
    public function invalidate(SettingScope $scope, string $scopeKey): void
    {
        unset($this->memory[$scope->value.'|'.$scopeKey]);
        $store = $this->store();

        if ($store === null) {
            return;
        }

        try {
            $store->forever($this->keys->stamp($this->generation($store), $scope, $scopeKey), bin2hex(random_bytes(8)));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Invalidate every cached settings value (all scopes, all workers).
     */
    public function flush(): void
    {
        $this->memory = [];
        $this->generation = null;
        $store = $this->store();

        try {
            $store?->forever($this->keys->generation(), bin2hex(random_bytes(8)));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Forget this request's memory (the next read consults the shared cache again).
     */
    public function forgetMemory(): void
    {
        $this->memory = [];
        $this->generation = null;
    }

    private function generation(CacheRepository $store): string
    {
        if ($this->generation === null) {
            $generation = $store->get($this->keys->generation());
            $this->generation = is_string($generation) ? $generation : '0';
        }

        return $this->generation;
    }

    /**
     * @param  array{scope: SettingScope, key: string}  $instance
     * @param  array<array-key, mixed>  $entry
     * @return array<string, StoredValue>
     */
    private function fromCache(array $instance, array $entry): array
    {
        $values = [];

        foreach ($entry as $key => $value) {
            $stored = is_string($key) ? StoredValue::fromCache($key, $instance['scope'], $instance['key'], $value) : null;

            if ($stored !== null) {
                $values[$key] = $stored;
            }
        }

        return $values;
    }

    private function store(): ?CacheRepository
    {
        if (! $this->config->cache) {
            return null;
        }

        try {
            return $this->cache->store($this->config->cacheStore);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
