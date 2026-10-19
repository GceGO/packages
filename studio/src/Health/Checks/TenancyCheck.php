<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Security\SecurityConfig;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Gcego\Studio\Integration\FrameworkCatalog;

/**
 * Tenancy is coherent: panels that need a tenant only exist when tenancy
 * is enabled, and enabled tenancy scopes at least one model.
 */
final readonly class TenancyCheck implements StudioHealthCheck
{
    public function __construct(private SecurityConfig $security, private FrameworkCatalog $catalog) {}

    public function id(): string
    {
        return 'security.tenancy';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $tenantPanels = array_values(array_filter(array_map(static fn (array $panel): ?string => in_array($panel['tenant'] ?? null, ['required', 'optional'], true) && is_string($panel['identity'] ?? null) ? $panel['identity'] : null, $this->catalog->panels())));

        if (! $this->security->tenancy) {
            return $tenantPanels === []
                ? HealthCheckResult::skipped($this->id(), 'Tenancy', 'Tenancy is not enabled (gcego-security.tenancy.enabled).', 'gcego/security')
                : HealthCheckResult::failure($this->id(), 'Tenancy', sprintf('%s use tenants but tenancy is disabled.', implode(', ', $tenantPanels)), 'Enable gcego-security.tenancy with a TenantResolver, or remove Panel::Tenant.', 'gcego/security');
        }

        $models = config('gcego-security.tenancy.models', []);

        if (! is_array($models) || $models === []) {
            return HealthCheckResult::warning($this->id(), 'Tenancy', 'Tenancy is enabled but no model is tenant-scoped (gcego-security.tenancy.models), so data is not isolated by tenant.', 'Map your tenant-owned models to their tenant key columns in gcego-security.tenancy.models.', 'gcego/security');
        }

        return HealthCheckResult::pass($this->id(), 'Tenancy', sprintf('Tenancy is enabled; %d model types are tenant-scoped.', count($models)), 'gcego/security');
    }
}
