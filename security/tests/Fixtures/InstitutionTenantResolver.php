<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures;

use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Security\Tests\Fixtures\Models\Institution;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An application adapter: institutions are tenants, active memberships grant
 * role-based permissions within one institution only. Read fresh on every call.
 */
final class InstitutionTenantResolver implements TenantResolver
{
    public const array RolePermissions = [
        'administrator' => ['members.*', 'subjects.view', 'subjects.manage', 'grades.update'],
        'teacher' => ['subjects.view', 'grades.update'],
        'assistant' => ['subjects.view'],
    ];

    public function find(string $key): ?Tenant
    {
        $institution = ctype_digit($key) ? Institution::query()->whereKey((int) $key)->where('active', true)->first() : null;

        return $institution === null ? null : new Tenant('institution', $institution->id, $institution, $institution->name);
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        $membership = Membership::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('institution_id', $tenant->key)
            ->where('status', 'active')
            ->first();

        return $membership === null ? null : new TenantMembership($tenant, $membership->user_id, [$membership->role], self::RolePermissions[$membership->role] ?? []);
    }
}
