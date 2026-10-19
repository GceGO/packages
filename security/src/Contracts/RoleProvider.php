<?php

declare(strict_types=1);

namespace Gcego\Security\Contracts;

use Gcego\Security\Authorization\AuthorizationContext;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The user's global (platform-wide) roles from the application's role system.
 * Tenant roles come from the TenantMembership, never from here.
 */
interface RoleProvider
{
    /**
     * @return list<string>
     */
    public function roles(Authenticatable $user, AuthorizationContext $context): array;
}
