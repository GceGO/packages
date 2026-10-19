<?php

declare(strict_types=1);

namespace Gcego\Vault;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Security\Contracts\PermissionProvider;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Roles\RoleResolver;
use Gcego\Security\SecurityConfig;
use Gcego\Vault\Assignments\AssignmentManager;
use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Authorization\GateIntegration;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Authorization\VaultPermissionProvider;
use Gcego\Vault\Authorization\VaultRoleProvider;
use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Caching\PermissionCache;
use Gcego\Vault\Caching\RequestMemo;
use Gcego\Vault\Console\AssignCommand;
use Gcego\Vault\Console\AuditCommand;
use Gcego\Vault\Console\CacheClearCommand;
use Gcego\Vault\Console\CheckCommand;
use Gcego\Vault\Console\ExpireCommand;
use Gcego\Vault\Console\InstallCommand;
use Gcego\Vault\Console\PermissionsCommand;
use Gcego\Vault\Console\PruneCommand;
use Gcego\Vault\Console\RolesCommand;
use Gcego\Vault\Console\SyncCommand;
use Gcego\Vault\Governance\AccessRequests;
use Gcego\Vault\Governance\Authority;
use Gcego\Vault\Governance\EscalationGuard;
use Gcego\Vault\Middleware\RequirePermission;
use Gcego\Vault\Permissions\PermissionDiscovery;
use Gcego\Vault\Permissions\PermissionRegistry;
use Gcego\Vault\Roles\RoleManager;
use Gcego\Vault\Support\Context;
use Gcego\Vault\Tenancy\VaultTenantResolver;
use Gcego\Navigation\NavigationManager;
use Gcego\Panels\PanelManager;
use Gcego\Vault\Integration\PanelVaultExtension;
use Gcego\Vault\Integration\VaultNavigationContributor;
use Gcego\Vault\Support\HolderReference;
use Gcego\Vault\Support\VaultStyles;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/vault; also the "gcego/vault" GCEGO package.
 *
 * register(): configuration and services, and Vault's place behind
 * gcego/security: the PermissionProvider (wrapping, not replacing, the
 * configured one), the RoleResolver's provider (only when the application
 * configured none) and the TenantResolver (wrapped, never replaced).
 * boot(): views, translations, migrations, the Gate after-callback, the
 * vault.permission middleware alias and the console commands.
 *
 * Lifetimes: configuration, the cache facade and stateless services are
 * singletons; everything that reads the current user, tenant or panel, or
 * remembers anything, is scoped (reset for every request and queued job,
 * Octane included). Nothing reads the database during register or boot.
 */
final class VaultServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-vault';

    /** Livewire components of the Vault screens (see Integration\Screens). */
    public const array Components = [
        'gcego-vault-roles' => \Gcego\Vault\Livewire\RolesIndex::class,
        'gcego-vault-role-editor' => \Gcego\Vault\Livewire\RoleEditor::class,
        'gcego-vault-role-details' => \Gcego\Vault\Livewire\RoleDetails::class,
        'gcego-vault-permissions' => \Gcego\Vault\Livewire\PermissionRegistryScreen::class,
        'gcego-vault-matrix' => \Gcego\Vault\Livewire\PermissionMatrix::class,
        'gcego-vault-people' => \Gcego\Vault\Livewire\PeopleIndex::class,
        'gcego-vault-person' => \Gcego\Vault\Livewire\PersonAccess::class,
        'gcego-vault-temporary' => \Gcego\Vault\Livewire\TemporaryAccess::class,
        'gcego-vault-requests' => \Gcego\Vault\Livewire\AccessRequestsScreen::class,
        'gcego-vault-approvals' => \Gcego\Vault\Livewire\ApprovalQueue::class,
        'gcego-vault-audit' => \Gcego\Vault\Livewire\AuditHistory::class,
        'gcego-vault-diagnostics' => \Gcego\Vault\Livewire\Diagnostics::class,
        'gcego-vault-settings' => \Gcego\Vault\Livewire\VaultSettings::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-vault.php', self::Name);

        $this->app->singleton(VaultConfig::class, static function (Application $app): VaultConfig {
            $config = $app->make(Repository::class);
            $vault = $config->get(self::Name, []);
            $user = $config->get('auth.providers.users.model');

            return VaultConfig::fromArray(is_array($vault) ? $vault : [], is_string($user) && class_exists($user) ? $user : null);
        });

        foreach ([PermissionCache::class, Context::class, Invalidation::class, Vault::class, PermissionDiscovery::class, AuditLog::class, GateIntegration::class, HolderReference::class, VaultStyles::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        foreach ([RequestMemo::class, PermissionEvaluator::class, Authority::class, EscalationGuard::class, PermissionRegistry::class, RoleManager::class, AssignmentManager::class, AccessRequests::class] as $scoped) {
            $this->app->scoped($scoped);
        }

        $this->app->extend(PermissionProvider::class, static function (PermissionProvider $provider, Application $app): PermissionProvider {
            $config = $app->make(VaultConfig::class);

            return $config->enabled && $config->securityPermissions && ! $provider instanceof VaultPermissionProvider ? new VaultPermissionProvider($app, $provider) : $provider;
        });

        $this->app->extend(TenantResolver::class, static function (TenantResolver $resolver, Application $app): TenantResolver {
            $config = $app->make(VaultConfig::class);

            return $config->enabled && $config->securityTenancy && ! $resolver instanceof VaultTenantResolver ? new VaultTenantResolver($resolver, $app) : $resolver;
        });

        // Only when the application configured no role provider of its own.
        $this->app->extend(RoleResolver::class, static function (RoleResolver $resolver, Application $app): RoleResolver {
            $config = $app->make(VaultConfig::class);

            return $config->enabled && $config->securityRoles && $app->make(SecurityConfig::class)->roleProvider === null ? new RoleResolver(new VaultRoleProvider($app)) : $resolver;
        });
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Name);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->loadMigrationsFrom($root.'/database/migrations');
        $this->publishes([$root.'/config/gcego-vault.php' => $this->app->configPath('gcego-vault.php')], 'gcego-vault-config');
        $this->publishes([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-vault-migrations');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/gcego-vault')], 'gcego-vault-views');

        // Validate now (configuration only: no user, no database), so unsafe settings fail at boot.
        $config = $this->app->make(VaultConfig::class);

        $this->app->make(Router::class)->aliasMiddleware(RequirePermission::Alias, RequirePermission::class);

        if ($config->enabled && $config->gate) {
            $this->app->make(GateIntegration::class)->register($this->app->make(Gate::class));
        }

        if ($config->enabled && $config->panels) {
            Blade::anonymousComponentPath($root.'/resources/views/components', self::Name);

            foreach (self::Components as $name => $component) {
                Livewire::component($name, $component);
            }

            if ($this->app->bound(PanelManager::class)) {
                $this->app->make(PanelManager::class)->extend(PanelVaultExtension::class);
            }

            if ($this->app->bound(NavigationManager::class)) {
                $this->app->make(NavigationManager::class)->contribute(VaultNavigationContributor::class);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, SyncCommand::class, RolesCommand::class, PermissionsCommand::class, CheckCommand::class, CacheClearCommand::class, AuditCommand::class, ExpireCommand::class, PruneCommand::class, AssignCommand::class]);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/vault',
            requires: ['gcego/core', 'gcego/registry', 'gcego/security', 'gcego/panels', 'gcego/modules', 'gcego/navigation', 'gcego/chroma'],
            capabilities: ['vault', 'vault.roles', 'vault.permissions', 'vault.assignments', 'vault.tenancy', 'vault.requests', 'vault.audit'],
            description: 'Roles, permissions, assignments and access governance: the storage and resolution layer behind gcego/security.',
        );
    }
}
