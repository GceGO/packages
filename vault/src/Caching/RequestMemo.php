<?php

declare(strict_types=1);

namespace Gcego\Vault\Caching;

use Closure;

/**
 * Per-request (and per-job) memory of resolved permission sets. Bound as a
 * scoped instance: Laravel empties it between requests and queued jobs
 * (Octane and queue workers included), so nothing leaks between users.
 */
final class RequestMemo
{
    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $key, Closure $compute): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        return $this->values[$key] = $compute();
    }

    public function flush(): void
    {
        $this->values = [];
    }

    public function count(): int
    {
        return count($this->values);
    }
}
