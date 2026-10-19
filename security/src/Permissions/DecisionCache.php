<?php

declare(strict_types=1);

namespace Gcego\Security\Permissions;

use Closure;
use Gcego\Security\Authorization\AuthorizationDecision;

/**
 * Request-scoped memo of permission decisions (bound as a scoped instance).
 *
 * Keys include the full context fingerprint (guard, user, tenant,
 * membership), so a decision is never reused for another user or tenant;
 * the cache starts empty in every request and job. Record and policy
 * decisions are never cached.
 */
final class DecisionCache
{
    /**
     * @var array<string, AuthorizationDecision>
     */
    private array $decisions = [];

    /**
     * @param  Closure(): AuthorizationDecision  $decide
     */
    public function remember(string $key, Closure $decide): AuthorizationDecision
    {
        return $this->decisions[$key] ??= $decide();
    }

    public function flush(): void
    {
        $this->decisions = [];
    }

    public function count(): int
    {
        return count($this->decisions);
    }
}
