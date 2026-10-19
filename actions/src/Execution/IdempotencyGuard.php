<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Gcego\Actions\ActionsConfig;
use Gcego\Security\Authorization\AuthorizationContext;
use Illuminate\Contracts\Cache\Repository;

/**
 * Duplicate-submission protection for Idempotent actions.
 *
 * The first request with a key claims it with an atomic cache add (scoped to
 * the action and user); later requests with the same key are rejected as
 * duplicates while the claim lasts. A failed run releases the key so the
 * caller may retry.
 *
 * Limits: this is at-most-once *per key and claim lifetime*, as atomic as the
 * cache store's add() (redis, database and memcached are atomic across
 * servers; the array store is per process). It is not exactly-once delivery:
 * a crash after the handler commits but before the response is sent leaves
 * the key claimed, and the client sees an error although the work happened.
 */
final readonly class IdempotencyGuard
{
    public function __construct(private Repository $cache, private ActionsConfig $config) {}

    public function claim(string $identity, AuthorizationContext $context, string $key): bool
    {
        return $this->cache->add($this->cacheKey($identity, $context, $key), time(), $this->config->idempotencyTtl);
    }

    public function release(string $identity, AuthorizationContext $context, string $key): void
    {
        $this->cache->forget($this->cacheKey($identity, $context, $key));
    }

    private function cacheKey(string $identity, AuthorizationContext $context, string $key): string
    {
        return 'gcego-actions:idempotency:'.hash('sha256', $identity."\0".$context->guard."\0".$context->userKey()."\0".$key);
    }
}
