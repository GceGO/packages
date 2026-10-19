<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Fixtures;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\TenantResolver as Contract;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Testing\Tests\Fixtures\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Institutions 1 and 2. Administrators may see secret notes.
 */
final class TenantResolver implements Contract
{
    public const array Permissions = ['administrator' => ['subjects.secrets', 'subjects.view'], 'teacher' => ['subjects.view'], 'student' => []];

    public function find(string $key): ?Tenant
    {
        return in_array($key, ['1', '2'], true) ? new Tenant('institution', (int) $key, null, 'Institution '.$key) : null;
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        $membership = Membership::query()->where('user_id', $user->getAuthIdentifier())->where('institution_id', $tenant->key)->first();

        if (! $membership instanceof Membership) {
            return null;
        }

        return new TenantMembership($tenant, AuthorizationContext::keyOf($user), [$membership->role], self::Permissions[$membership->role] ?? []);
    }
}
