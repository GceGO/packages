<?php

declare(strict_types=1);

namespace Gcego\Vault\Authorization;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\RoleProvider;
use Gcego\Vault\Support\Context;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * The user's global Vault roles for gcego/security's RoleResolver (scope
 * providers, diagnostics). Roles describe; permissions decide.
 */
final readonly class VaultRoleProvider implements RoleProvider
{
    public function __construct(private Container $container) {}

    public function roles(Authenticatable $user, AuthorizationContext $context): array
    {
        if (! $user instanceof Model) {
            return [];
        }

        $effective = $this->container->make(PermissionEvaluator::class)->effective($user, '', $this->container->make(Context::class)->panel());

        return [...$effective->roles, ...$effective->inherited];
    }
}
