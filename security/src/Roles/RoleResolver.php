<?php

declare(strict_types=1);

namespace Gcego\Security\Roles;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\RoleProvider;

/**
 * Context-aware role information for scope providers and diagnostics.
 */
final readonly class RoleResolver
{
    public function __construct(private ?RoleProvider $provider = null) {}

    public function roles(AuthorizationContext $context): RoleSet
    {
        if ($context->user === null) {
            return new RoleSet;
        }

        return new RoleSet(
            global: $this->provider?->roles($context->user, $context) ?? [],
            tenant: $context->membership->roles ?? [],
            tenantId: $context->tenant?->id(),
        );
    }
}
