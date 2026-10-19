<?php

declare(strict_types=1);

namespace Gcego\Testing\Concerns;

use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Source;
use Gcego\Testing\Fixtures\TenantFixture;
use Gcego\Testing\GcegoTest;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait InteractsWithGcegoTenancy
{
    protected function enterGcegoTenant(string|int|TenantFixture $tenant): void
    {
        GcegoTest::manager()->environment()->enterTenant((string) ($tenant instanceof TenantFixture ? $tenant->key : $tenant));
    }

    protected function leaveGcegoTenant(): void
    {
        GcegoTest::manager()->environment()->leaveTenant();
    }

    public function assertGcegoCannotAccessTenant(string|int|TenantFixture $tenant): void
    {
        GcegoTest::tenant($this->currentGcegoTenant())->assertCannotAccessTenant($tenant);
    }

    /**
     * None of these records is visible in the current tenant.
     *
     * @param  iterable<string|int|Model>  $keys
     */
    public function assertGcegoTenantIsolated(Source|SourceDefinition $source, iterable $keys): void
    {
        GcegoTest::tenant($this->currentGcegoTenant())->assertCannotSee($source, $keys);
    }

    private function currentGcegoTenant(): string
    {
        return GcegoTest::manager()->environment()->currentTenant() ?? throw new LogicException('No tenant is selected: call actingAsGcego($user, $tenant) first.');
    }
}
