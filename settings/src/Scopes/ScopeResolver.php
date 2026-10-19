<?php

declare(strict_types=1);

namespace Gcego\Settings\Scopes;

use Gcego\Panels\PanelManager;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Settings\Exceptions\SettingScopeException;
use Gcego\Settings\SettingsConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Builds ScopeContexts from trusted server state:
 *
 * - the tenant: gcego/security's TenantContext (set only after the
 *   request's membership was verified), or a tenant trusted code names;
 * - the panel: gcego/panels' verified PanelContext of the request;
 * - the user: the signed-in user of the gcego/security guard, or a user
 *   trusted code passes.
 *
 * Request parameters and Livewire state never choose a scope identity.
 * Resolved per request or job (scoped), so nothing leaks between tenants
 * or users in long-running workers.
 */
final readonly class ScopeResolver
{
    public function __construct(
        private Container $container,
        private TenantContext $tenants,
        private SecurityConfig $security,
        private SettingsConfig $config,
        private AuthFactory $auth,
    ) {}

    /**
     * The request's own context: every known level, the most specific as target.
     */
    public function current(): ScopeContext
    {
        $panel = $this->currentPanel();
        $tenant = $this->tenants->tenant();
        $user = $this->currentUser();
        $target = match (true) {
            $user !== null => SettingScope::User,
            $tenant !== null => SettingScope::Tenant,
            $panel !== null => SettingScope::Panel,
            default => SettingScope::Global,
        };

        return new ScopeContext($target, $panel, $tenant, $user === null ? null : self::userKey($user));
    }

    public function global(): ScopeContext
    {
        return new ScopeContext(SettingScope::Global);
    }

    public function forPanel(string $panel): ScopeContext
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/', $panel) !== 1) {
            throw SettingScopeException::because('A panel scope needs a panel name.');
        }

        return new ScopeContext(SettingScope::Panel, $panel);
    }

    /**
     * A tenant named by trusted code: a gcego/security Tenant, the tenant's model, or its key.
     *
     * @throws SettingScopeException when tenancy is off or the tenant is unknown
     */
    public function forTenant(Tenant|Model|string|int $tenant): ScopeContext
    {
        return new ScopeContext(SettingScope::Tenant, $this->currentPanel(), $this->tenant($tenant));
    }

    /**
     * A user's preferences, inheriting from the request's tenant and panel.
     */
    public function forUser(Authenticatable $user): ScopeContext
    {
        return new ScopeContext(SettingScope::User, $this->currentPanel(), $this->tenants->tenant(), self::userKey($user));
    }

    /**
     * The context a settings screen edits at a scope, from the request alone.
     *
     * @throws SettingScopeException when the request has no such level (no verified tenant, panel or user)
     */
    public function forScreen(SettingScope $scope): ScopeContext
    {
        $context = $this->current()->at($scope);

        if ($context->targetKey() === null) {
            throw SettingScopeException::because(sprintf('This page edits %s settings, which need %s.', $scope->value, match ($scope) {
                SettingScope::Tenant => 'a verified tenant (open it inside an institution)',
                SettingScope::Panel => 'a panel',
                SettingScope::User => 'a signed-in user',
                SettingScope::Global => 'nothing',
            }));
        }

        return $context;
    }

    /**
     * @throws SettingScopeException
     */
    public function tenant(Tenant|Model|string|int $tenant): Tenant
    {
        if (! $this->security->tenancy) {
            throw SettingScopeException::because('Tenant settings need gcego/security tenancy (gcego-security.tenancy.enabled).');
        }

        if (! $this->config->enables(SettingScope::Tenant)) {
            throw SettingScopeException::because('The tenant scope is disabled (gcego-settings.scopes.tenant).');
        }

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        $key = $tenant instanceof Model ? $tenant->getKey() : $tenant;

        if (! is_string($key) && ! is_int($key)) {
            throw SettingScopeException::because('A tenant needs a string or integer key.');
        }

        try {
            $resolved = $this->container->make(TenantResolver::class)->find((string) $key);
        } catch (Throwable) {
            $resolved = null;
        }

        return $resolved ?? throw SettingScopeException::because('The tenant does not exist.');
    }

    /**
     * The scope key of a user: their morph class (or class) and identifier.
     */
    public static function userKey(Authenticatable $user): string
    {
        $type = $user instanceof Model ? $user->getMorphClass() : $user::class;

        return mb_substr($type.'#'.AuthorizationContext::keyOf($user), 0, 191);
    }

    private function currentUser(): ?Authenticatable
    {
        try {
            return $this->auth->guard($this->security->guard)->user();
        } catch (Throwable) {
            return null;
        }
    }

    private function currentPanel(): ?string
    {
        if (! $this->config->enables(SettingScope::Panel) || ! $this->container->bound(PanelManager::class)) {
            return null;
        }

        try {
            return $this->container->make(PanelManager::class)->current()?->name();
        } catch (Throwable) {
            return null;
        }
    }
}
