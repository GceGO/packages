<?php

declare(strict_types=1);

namespace Gcego\Security\Permissions;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\PermissionProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Permissions as Gate abilities: Gate::define('subjects.view', ...), Spatie's
 * Gate integration, and the application's own Gate::before/after rules
 * (for example a super-administrator rule) all apply. An ability nobody
 * defines or grants is denied.
 */
final readonly class GatePermissionProvider implements PermissionProvider
{
    public function __construct(private Gate $gate) {}

    public function allows(Authenticatable $user, string $permission, AuthorizationContext $context): bool
    {
        return $this->gate->forUser($user)->allows($permission);
    }
}
