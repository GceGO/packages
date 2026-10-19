<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures;

use Gcego\Actions\Tests\Fixtures\Models\Membership;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\TenantResolver as Contract;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Institutions 1 and 2 are tenants; memberships are rows in "memberships".
 */
final class TenantResolver implements Contract
{
    public function find(string $key): ?Tenant
    {
        return in_array($key, ['1', '2'], true) ? new Tenant('institution', (int) $key) : null;
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        $exists = Membership::query()->where('user_id', $user->getAuthIdentifier())->where('institution_id', $tenant->key)->where('status', 'active')->exists();

        return $exists ? new TenantMembership($tenant, AuthorizationContext::keyOf($user), ['member'], ['subjects.*']) : null;
    }
}
