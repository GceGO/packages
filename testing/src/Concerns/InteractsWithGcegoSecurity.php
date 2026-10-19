<?php

declare(strict_types=1);

namespace Gcego\Testing\Concerns;

use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Source;
use Gcego\Testing\GcegoTest;
use Illuminate\Contracts\Auth\Authenticatable;

trait InteractsWithGcegoSecurity
{
    /**
     * Signs the user in and enters the tenant (membership verified).
     */
    protected function actingAsGcego(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): static
    {
        GcegoTest::manager()->environment()->actingAs($user, $tenant, $guard);

        return $this;
    }

    /**
     * A new simulated request: per-request services are rebuilt.
     */
    protected function newGcegoRequest(): void
    {
        GcegoTest::manager()->environment()->newRequest();
    }

    public function assertGcegoCan(string $ability, mixed $resource = null): void
    {
        GcegoTest::security()->assertCan($ability, $resource);
    }

    public function assertGcegoCannot(string $ability, mixed $resource = null): void
    {
        GcegoTest::security()->assertCannot($ability, $resource);
    }

    public function assertGcegoRecordHidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void
    {
        GcegoTest::security()->assertRecordHidden($source, $key, $ability);
    }

    public function assertGcegoRecordForbidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void
    {
        GcegoTest::security()->assertRecordForbidden($source, $key, $ability);
    }
}
