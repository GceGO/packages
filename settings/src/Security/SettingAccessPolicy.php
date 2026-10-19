<?php

declare(strict_types=1);

namespace Gcego\Settings\Security;

use Gcego\Security\Contracts\Requirement;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\SettingsConfig;
use Gcego\Settings\SettingsPage;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * The default SettingsAuthorizer. Deny by default; errors deny.
 *
 * | Target | Allowed when |
 * | ------ | ------------ |
 * | any    | the setting allows the scope, and a person is signed in |
 * | global, panel | the setting's requirements (its own, its section's and its group's) all pass, or the person has the Gate ability "gcego-settings.manage" |
 * | tenant | the person holds a verified membership of that very tenant, and that membership grants "settings.manage" or the requirements pass inside the tenant |
 * | user   | it is the person's own preference (and its requirements, if any, pass) |
 *
 * The platform ability never reaches into a tenant: a tenant's values are
 * changed only by its own members. Viewing needs the same rights as
 * changing; sensitive values are never shown, only whether one is set.
 * Export and import also need "gcego-settings.export" / ".import" (a Gate
 * ability, or for a tenant, a permission of the membership).
 */
final class SettingAccessPolicy implements SettingsAuthorizer
{
    /** @var array<string, TenantMembership|null> Memberships verified in this request (scoped instance). */
    private array $memberships = [];

    public function __construct(
        private readonly Gate $gate,
        private readonly SecurityManager $security,
        private readonly SecurityConfig $securityConfig,
        private readonly TenantAuthorizer $tenants,
        private readonly SettingsConfig $config,
    ) {}

    public function canView(?Authenticatable $user, SettingDefinition $definition, ScopeContext $context): bool
    {
        return $this->canUpdate($user, $definition, $context);
    }

    public function canUpdate(?Authenticatable $user, SettingDefinition $definition, ScopeContext $context): bool
    {
        if ($user === null || ! $definition->allows($context->target) || $context->targetKey() === null) {
            return false;
        }

        return match ($context->target) {
            SettingScope::Global, SettingScope::Panel => ($definition->requirements !== [] && $this->satisfies($user, $definition->requirements, null)) || $this->ability($user, $this->config->manageAbility),
            SettingScope::Tenant => $this->tenantAllows($user, $context->tenant, $definition->requirements),
            SettingScope::User => $context->user === ScopeResolver::userKey($user) && ($definition->requirements === [] || $this->satisfies($user, $definition->requirements, $context->tenant)),
        };
    }

    public function canOpen(?Authenticatable $user, SettingsPage $page, ScopeContext $context): bool
    {
        if ($user === null || $context->targetKey() === null) {
            return false;
        }

        if ($context->target === SettingScope::Tenant && $this->membership($user, $context->tenant) === null) {
            return false;
        }

        return $page->requirements() === [] || $this->satisfies($user, $page->requirements(), $context->tenant);
    }

    public function canExport(?Authenticatable $user, ScopeContext $context): bool
    {
        return $this->transfer($user, $context, $this->config->exportAbility);
    }

    public function canImport(?Authenticatable $user, ScopeContext $context): bool
    {
        return $this->transfer($user, $context, $this->config->importAbility);
    }

    private function transfer(?Authenticatable $user, ScopeContext $context, string $ability): bool
    {
        if ($user === null || $context->targetKey() === null) {
            return false;
        }

        if ($context->target !== SettingScope::Tenant) {
            return $this->ability($user, $ability);
        }

        $membership = $this->membership($user, $context->tenant);

        return $membership !== null && $membership->grants($this->config->tenantPermission) && ($membership->grants($ability) || $this->ability($user, $ability));
    }

    /**
     * @param  list<Requirement>  $requirements
     */
    private function tenantAllows(Authenticatable $user, ?Tenant $tenant, array $requirements): bool
    {
        $membership = $this->membership($user, $tenant);

        if ($membership === null) {
            return false;
        }

        return $membership->grants($this->config->tenantPermission) || ($requirements !== [] && $this->satisfies($user, $requirements, $tenant));
    }

    private function membership(Authenticatable $user, ?Tenant $tenant): ?TenantMembership
    {
        if ($tenant === null || ! $this->securityConfig->tenancy) {
            return null;
        }

        $key = ScopeResolver::userKey($user).'@'.$tenant->id();

        if (array_key_exists($key, $this->memberships)) {
            return $this->memberships[$key];
        }

        try {
            $membership = $this->tenants->verify($user, (string) $tenant->key, $this->securityConfig->guard ?? 'web');

            return $this->memberships[$key] = $membership->tenant->is($tenant) ? $membership : null;
        } catch (Throwable) {
            return $this->memberships[$key] = null;
        }
    }

    /**
     * @param  list<Requirement>  $requirements
     */
    private function satisfies(Authenticatable $user, array $requirements, ?Tenant $tenant): bool
    {
        try {
            return $this->security->actingAs($user, $tenant === null ? null : (string) $tenant->key, fn (): bool => ! $this->security->check(...$requirements)->denied());
        } catch (Throwable) {
            return false;
        }
    }

    private function ability(Authenticatable $user, string $ability): bool
    {
        try {
            $gate = $this->gate->forUser($user);

            return $gate->has($ability) && $gate->allows($ability);
        } catch (Throwable) {
            return false;
        }
    }
}
