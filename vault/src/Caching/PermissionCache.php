<?php

declare(strict_types=1);

namespace Gcego\Vault\Caching;

use Closure;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cross-request cache of the access graph and of effective permission sets.
 *
 * Every key embeds version tokens: a global one (any role, permission or
 * inheritance change) and one per holder (their assignments and grants).
 * Changing access replaces the token, so no stale entry is ever read again;
 * tokens are random, so an evicted token can never bring an old entry back.
 * Tokens are replaced when the change is made and again after the
 * transaction commits, so a concurrent reader cannot cache pre-change data
 * under the new token. Entries also carry a TTL bounded by the earliest
 * expiry of the temporary access they contain.
 *
 * Holds no per-request state: safe as a singleton in long-running workers.
 */
final readonly class PermissionCache
{
    public function __construct(
        private Factory $cache,
        private VaultConfig $config,
        private ConnectionResolverInterface $database,
    ) {}

    public function enabled(): bool
    {
        return $this->config->cache;
    }

    public function globalVersion(): string
    {
        return $this->version($this->key('v', 'global'));
    }

    public function holderVersion(string $type, string $id): string
    {
        return $this->version($this->key('v', 'h', sha1($type.'|'.$id)));
    }

    /**
     * Invalidate everything (roles, permissions or inheritance changed).
     */
    public function touchGlobal(): void
    {
        $this->touch($this->key('v', 'global'));
    }

    /**
     * Invalidate one holder's permission sets (an assignment or grant changed).
     */
    public function touchHolder(string $type, string $id): void
    {
        $this->touch($this->key('v', 'h', sha1($type.'|'.$id)));
    }

    public function get(string $key): mixed
    {
        if (! $this->config->cache) {
            return null;
        }

        try {
            return $this->store()->get($key);
        } catch (Throwable) {
            return null;
        }
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        if (! $this->config->cache) {
            return;
        }

        try {
            $this->store()->put($key, $value, max(1, min($ttl, $this->config->cacheTtl)));
        } catch (Throwable) {
            // A failing cache only costs performance, never correctness.
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $key, int $ttl, Closure $compute): mixed
    {
        if (! $this->config->cache) {
            return $compute();
        }

        try {
            $hit = $this->store()->get($key);
        } catch (Throwable) {
            return $compute();
        }

        if ($hit !== null) {
            return $hit;
        }

        $value = $compute();

        try {
            $this->store()->put($key, $value, max(1, min($ttl, $this->config->cacheTtl)));
        } catch (Throwable) {
            // A failing cache only costs performance, never correctness.
        }

        return $value;
    }

    public function key(string|int ...$parts): string
    {
        return $this->config->cachePrefix.':'.implode(':', $parts);
    }

    private function version(string $key): string
    {
        if (! $this->config->cache) {
            return '-';
        }

        try {
            $version = $this->store()->get($key);

            if (is_string($version) && $version !== '') {
                return $version;
            }

            $version = Str::random(16);
            $this->store()->forever($key, $version);

            return $version;
        } catch (Throwable) {
            // Without a working cache nothing is reused: a fresh token per call.
            return Str::random(16);
        }
    }

    private function touch(string $key): void
    {
        if (! $this->config->cache) {
            return;
        }

        $replace = function () use ($key): void {
            try {
                $this->store()->forever($key, Str::random(16));
            } catch (Throwable) {
                // Nothing cached can be read without the same token; a failing store reads nothing.
            }
        };

        $replace();

        $connection = $this->database->connection($this->config->connection);

        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit($replace);
        }
    }

    private function store(): Repository
    {
        return $this->cache->store($this->config->cacheStore);
    }
}
