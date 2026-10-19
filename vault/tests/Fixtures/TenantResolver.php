<?php

declare(strict_types=1);

namespace Gcego\Vault\Tests\Fixtures;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\TenantResolver as Contract;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Vault\Tests\Fixtures\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Institutions 1 (North) and 2 (South); membership comes from the application's own table.
 */
final class TenantResolver implements Contract
{
    public function find(string $key): ?Tenant
    {
        return in_array($key, ['1', '2'], true) ? new Tenant('institution', (int) $key, name: $key === '1' ? 'North College' : 'South College') : null;
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        return Membership::query()->where('user_id', $user->getAuthIdentifier())->where('institution_id', $tenant->key)->exists()
            ? new TenantMembership($tenant, AuthorizationContext::keyOf($user), ['member'], [])
            : null;
    }
}
