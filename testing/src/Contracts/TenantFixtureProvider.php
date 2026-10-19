<?php

declare(strict_types=1);

namespace Gcego\Testing\Contracts;

use Gcego\Testing\Fixtures\TenantFixture;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Creates tenants and memberships with the application's own models.
 *
 * gcego/security only knows tenants through the application's
 * TenantResolver, so the toolkit cannot create them itself: implement this
 * once (it is usually a few lines over existing factories) and register it
 * in gcego-testing.fixtures.tenants.
 */
interface TenantFixtureProvider
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): TenantFixture;

    /**
     * Makes the user a member of the tenant. Roles and permissions are
     * whatever the application's TenantResolver understands.
     *
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function join(Authenticatable $user, TenantFixture $tenant, array $roles = [], array $permissions = []): void;
}
