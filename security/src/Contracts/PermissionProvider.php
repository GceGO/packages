<?php

declare(strict_types=1);

namespace Gcego\Security\Contracts;

use Gcego\Security\Authorization\AuthorizationContext;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Answers "does this user hold this global (non-tenant) permission?" from the
 * application's existing permission infrastructure (Gate abilities, Spatie, ...).
 * Tenant permissions never come from here: they come from the TenantMembership.
 */
interface PermissionProvider
{
    /**
     * @return bool|null True or false, or null when the permission is unknown to the provider (treated as a denial).
     */
    public function allows(Authenticatable $user, string $permission, AuthorizationContext $context): ?bool;
}
