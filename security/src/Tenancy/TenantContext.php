<?php

declare(strict_types=1);

namespace Gcego\Security\Tenancy;

use Closure;
use Gcego\Security\Exceptions\MissingAuthorizationContextException;

/**
 * The tenant the current request (or job) acts for, and the verified membership it acts through.
 *
 * Bound as a scoped instance: empty at the start of every request and every
 * queued job, so a tenant never leaks between them (Octane included). Only
 * TenantAuthorizer (after verifying membership) and trusted system code set it;
 * a request parameter never sets it by itself.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?TenantMembership $membership = null;

    private int $version = 0;

    public function set(Tenant $tenant, ?TenantMembership $membership = null): void
    {
        if ($membership !== null && ! $membership->tenant->is($tenant)) {
            throw MissingAuthorizationContextException::because('The membership belongs to another tenant.');
        }

        $this->tenant = $tenant;
        $this->membership = $membership;
        $this->version++;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->membership = null;
        $this->version++;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function membership(): ?TenantMembership
    {
        return $this->membership;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Changes whenever the tenant changes; part of decision cache keys.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * Run with another tenant, restoring the previous one afterwards, even on failure.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(?Tenant $tenant, ?TenantMembership $membership, Closure $callback): mixed
    {
        [$previousTenant, $previousMembership] = [$this->tenant, $this->membership];
        $tenant === null ? $this->clear() : $this->set($tenant, $membership);

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->membership = $previousMembership;
            $this->version++;
        }
    }
}
