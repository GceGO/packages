<?php

declare(strict_types=1);

namespace Gcego\Vault\Caching;

use Gcego\Security\Permissions\DecisionCache;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Support\Holder;
use Illuminate\Contracts\Container\Container;

/**
 * Makes a change take effect at once: replaces the version tokens (other
 * requests and workers stop reading old entries) and empties this request's
 * memory, Vault's and gcego/security's decision cache alike.
 */
final readonly class Invalidation
{
    public function __construct(private PermissionCache $cache, private Container $container) {}

    /**
     * Roles, permissions or inheritance changed: everyone's permissions may differ.
     */
    public function definitions(): void
    {
        $this->cache->touchGlobal();
        $this->forget();
    }

    /**
     * One holder's assignments or grants changed.
     */
    public function holder(Holder $holder): void
    {
        $this->cache->touchHolder($holder->type, $holder->id);
        $this->forget();
    }

    public function forget(): void
    {
        $this->container->make(PermissionEvaluator::class)->forget();

        if ($this->container->bound(DecisionCache::class)) {
            $this->container->make(DecisionCache::class)->flush();
        }
    }
}
