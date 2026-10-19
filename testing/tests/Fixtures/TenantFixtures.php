<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Fixtures;

use Gcego\Testing\Contracts\TenantFixtureProvider;
use Gcego\Testing\Fixtures\TenantFixture;
use Gcego\Testing\Tests\Fixtures\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The application-side adapter the toolkit's tenant factory calls: here
 * institutions are fixed (1 and 2), so create() hands them out in order.
 */
final class TenantFixtures implements TenantFixtureProvider
{
    private int $next = 1;

    public function create(array $attributes = []): TenantFixture
    {
        $key = is_scalar($attributes['key'] ?? null) ? (string) $attributes['key'] : (string) $this->next++;

        return new TenantFixture($key, null, 'Institution '.$key);
    }

    public function join(Authenticatable $user, TenantFixture $tenant, array $roles = [], array $permissions = []): void
    {
        Membership::query()->create(['user_id' => $user->getAuthIdentifier(), 'institution_id' => (int) $tenant->key, 'role' => $roles[0] ?? 'teacher']);
    }
}
