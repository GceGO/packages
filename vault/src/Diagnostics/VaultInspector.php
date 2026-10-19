<?php

declare(strict_types=1);

namespace Gcego\Vault\Diagnostics;

use Gcego\Security\Contracts\PermissionProvider;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Vault\Authorization\VaultPermissionProvider;
use Gcego\Vault\Enums\RequestStatus;
use Gcego\Vault\Models\AccessRequest;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Tenancy\VaultTenantResolver;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;

/**
 * Health of the Vault installation in one scope: counts, and checks that
 * point at real problems (a protected role with no holder, permissions no
 * longer declared, expired records not closed, integration not active).
 * Holds identifiers and counts only.
 */
final readonly class VaultInspector
{
    public function __construct(private Container $container, private VaultConfig $config) {}

    /**
     * @return array{counts: array<string, int>, checks: list<array{status: string, message: string}>}
     */
    public function report(string $tenant = ''): array
    {
        $latest = Permission::query()->max('declared_at');
        $stale = $latest === null ? 0 : Permission::query()->where('source', '!=', 'custom')->where(static fn ($query) => $query->whereNull('declared_at')->orWhere('declared_at', '<', $latest))->count();
        $unclosed = Assignment::query()->whereNotNull('active_key')->whereNotNull('expires_at')->where('expires_at', '<=', Date::now())->count()
            + Grant::query()->whereNotNull('active_key')->whereNotNull('expires_at')->where('expires_at', '<=', Date::now())->count();
        $counts = [
            'roles' => Role::query()->availableIn($tenant)->whereNull('archived_at')->count(),
            'permissions' => Permission::query()->count(),
            'assignments' => Assignment::query()->where('tenant', $tenant)->active()->count(),
            'grants' => Grant::query()->where('tenant', $tenant)->active()->count(),
            'temporary' => Assignment::query()->where('tenant', $tenant)->active()->whereNotNull('expires_at')->count() + Grant::query()->where('tenant', $tenant)->active()->whereNotNull('expires_at')->count(),
            'pending_requests' => AccessRequest::query()->where('tenant', $tenant)->where('status', RequestStatus::Pending)->count(),
        ];
        $checks = [];

        $checks[] = $counts['permissions'] === 0
            ? ['status' => 'error', 'message' => 'No permission is registered. Run "php artisan gcego:vault:sync".']
            : ['status' => 'ok', 'message' => sprintf('%d permissions are registered.', $counts['permissions'])];

        foreach (Role::query()->where('is_protected', true)->whereNull('archived_at')->get() as $role) {
            if (! Assignment::query()->where('role_id', $role->id)->active()->exists()) {
                $checks[] = ['status' => 'error', 'message' => sprintf('The protected role "%s" has no active holder. Recover with "php artisan gcego:vault:assign <user> %s".', $role->name, $role->name)];
            }
        }

        if ($stale > 0) {
            $checks[] = ['status' => 'warning', 'message' => sprintf('%d permissions are no longer declared by the application ("gcego:vault:sync --prune" removes the unused ones).', $stale)];
        }

        if ($unclosed > 0) {
            $checks[] = ['status' => 'info', 'message' => sprintf('%d expired records are not closed yet (they already grant nothing). Schedule "php artisan gcego:vault:expire".', $unclosed)];
        }

        $checks[] = $this->container->make(PermissionProvider::class) instanceof VaultPermissionProvider
            ? ['status' => 'ok', 'message' => 'gcego/security permission requirements are answered by Vault.']
            : ['status' => 'warning', 'message' => 'gcego/security does not ask Vault for permissions (gcego-vault.security.permissions is off).'];
        $checks[] = $this->container->make(TenantResolver::class) instanceof VaultTenantResolver
            ? ['status' => 'ok', 'message' => 'Tenant memberships include Vault tenant roles.']
            : ['status' => 'info', 'message' => 'Tenant memberships do not include Vault roles (gcego-vault.security.tenancy is off).'];
        $checks[] = $this->config->superAdmin
            ? ['status' => 'warning', 'message' => 'Super-administrator roles are enabled: they get every registered global permission except '.($this->config->superExcluded === [] ? 'none excluded' : implode(', ', $this->config->superExcluded)).'.']
            : ['status' => 'ok', 'message' => 'Super-administrator roles are disabled; nobody bypasses explicit grants.'];
        $checks[] = $this->config->cache
            ? ['status' => 'ok', 'message' => sprintf('Permission sets are cached for at most %d seconds and invalidated on every change.', $this->config->cacheTtl)]
            : ['status' => 'info', 'message' => 'Permission caching is off: every request recomputes permissions.'];

        return ['counts' => $counts, 'checks' => $checks];
    }
}
