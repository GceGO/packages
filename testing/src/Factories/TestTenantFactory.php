<?php

declare(strict_types=1);

namespace Gcego\Testing\Factories;

use Gcego\Testing\Contracts\TenantFixtureProvider;
use Gcego\Testing\Exceptions\InvalidFixtureException;
use Gcego\Testing\Fixtures\TenantFixture;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Creates tenants and memberships through the application's
 * TenantFixtureProvider (gcego-testing.fixtures.tenants).
 *
 * There is deliberately no default: tenants are whatever the application's
 * TenantResolver says they are, and guessing a schema would create a
 * second, competing identity model.
 */
final readonly class TestTenantFactory
{
    public function __construct(private ?TenantFixtureProvider $provider = null) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): TenantFixture
    {
        return $this->provider()->create($attributes);
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function join(Authenticatable $user, TenantFixture $tenant, array $roles = [], array $permissions = []): Authenticatable
    {
        $this->provider()->join($user, $tenant, $roles, $permissions);

        return $user;
    }

    public function available(): bool
    {
        return $this->provider !== null;
    }

    private function provider(): TenantFixtureProvider
    {
        return $this->provider ?? throw InvalidFixtureException::invalid('tenants', 'needs a TenantFixtureProvider: implement Gcego\Testing\Contracts\TenantFixtureProvider and set it in gcego-testing.fixtures.tenants');
    }
}
