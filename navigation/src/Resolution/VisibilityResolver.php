<?php

declare(strict_types=1);

namespace Gcego\Navigation\Resolution;

use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Requirements\Authenticated;

/**
 * Decides whether the current user may see a node, from the requirements of
 * the node and every ancestor (gcego/security). A chain without any
 * requirement falls back to gcego-navigation.visibility.default. Decisions
 * are memoized per chain and security context.
 *
 * Visibility is a usability feature: the linked routes stay protected by
 * their own authorization.
 */
final class VisibilityResolver
{
    /** @var array<string, bool> */
    private array $decisions = [];

    public function __construct(private readonly AuthorizationEngine $engine, private readonly string $default) {}

    /**
     * @param  list<Requirement>  $chain
     * @param  string  $fingerprint  The security context the decision is for (decisions never cross users or tenants).
     */
    public function allows(array $chain, string $fingerprint): bool
    {
        if ($chain === []) {
            return match ($this->default) {
                'public' => true,
                'deny' => false,
                default => $this->evaluate($fingerprint.'|default', [new Authenticated]),
            };
        }

        return $this->evaluate($fingerprint.'|'.implode(',', array_map(spl_object_id(...), $chain)), $chain);
    }

    /**
     * @param  list<Requirement>  $chain
     */
    private function evaluate(string $key, array $chain): bool
    {
        return $this->decisions[$key] ??= $this->engine->requirements($chain, null, null, 'view')->allowed;
    }
}
