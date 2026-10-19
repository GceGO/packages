<?php

declare(strict_types=1);

namespace Gcego\Security;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Data\Contracts\DataAuthorizer;
use Gcego\Data\Security\GateDataAuthorizer;
use Gcego\Data\Security\ScopeRegistry;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Gcego\Runtime\Security\GateRenderAuthorizer;
use Gcego\Security\Audit\LogAuditReporter;
use Gcego\Security\Audit\NullAuditReporter;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\AuthorizerRegistry;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Contracts\AuditReporter;
use Gcego\Security\Contracts\PermissionProvider;
use Gcego\Security\Contracts\RoleProvider;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Diagnostics\SecurityInspector;
use Gcego\Security\Fields\FieldAuthorizer;
use Gcego\Security\Integration\SecurityDataAuthorizer;
use Gcego\Security\Integration\SecurityDataScope;
use Gcego\Security\Integration\SecurityRenderAuthorizer;
use Gcego\Security\Middleware\AuthorizeDefinition;
use Gcego\Security\Middleware\ResolveTenant;
use Gcego\Security\Operations\OperationAuthorizer;
use Gcego\Security\Permissions\DecisionCache;
use Gcego\Security\Permissions\GatePermissionProvider;
use Gcego\Security\Permissions\PermissionResolver;
use Gcego\Security\Permissions\SpatiePermissionProvider;
use Gcego\Security\Records\RecordAuthorizer;
use Gcego\Security\Resources\DefinitionAuthorizer;
use Gcego\Security\Roles\RoleResolver;
use Gcego\Security\Roles\SpatieRoleProvider;
use Gcego\Security\Tenancy\NullTenantResolver;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/security; also the "gcego/security" GCEGO package.
 *
 * Lifetimes: registrations (AuthorizerRegistry, providers, config) are
 * singletons; everything that depends on the current user or tenant is a
 * scoped instance, reset by Laravel for every request and job (Octane
 * included). Nothing reads the user or the database during registration or boot.
 *
 * It replaces gcego/runtime's GateRenderAuthorizer and gcego/data's
 * GateDataAuthorizer (only those defaults; application bindings are kept),
 * adds its data scope to gcego/data, and registers the middleware aliases
 * gcego.authorize and gcego.tenant without applying them to any route.
 */
final class SecurityServiceProvider extends ServiceProvider implements PackageInterface
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-security.php', 'gcego-security');

        $this->app->singleton(SecurityConfig::class, static function (Application $app): SecurityConfig {
            $config = $app->make(Repository::class)->get('gcego-security', []);

            return SecurityConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(AuthorizerRegistry::class);
        $this->app->singleton(TenantResolver::class, static function (Application $app): TenantResolver {
            $resolver = $app->make(SecurityConfig::class)->tenantResolver;
            $instance = $resolver === null ? new NullTenantResolver : $app->make($resolver);

            return $instance instanceof TenantResolver ? $instance : new NullTenantResolver;
        });
        $this->app->singleton(PermissionProvider::class, static function (Application $app): PermissionProvider {
            $provider = $app->make(SecurityConfig::class)->permissionProvider;
            $instance = match ($provider) {
                'gate' => $app->make(GatePermissionProvider::class),
                'spatie' => new SpatiePermissionProvider,
                default => $app->make($provider),
            };

            return $instance instanceof PermissionProvider ? $instance : $app->make(GatePermissionProvider::class);
        });
        $this->app->singleton(AuditReporter::class, static function (Application $app): AuditReporter {
            $config = $app->make(SecurityConfig::class);

            return $config->audit ? new LogAuditReporter($app->make('log'), $config->auditChannel) : new NullAuditReporter;
        });

        // Request-scoped: reset for every request and job.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(DecisionCache::class);
        $this->app->scoped(ContextFactory::class);
        $this->app->scoped(PermissionResolver::class, static fn (Application $app): PermissionResolver => new PermissionResolver(
            $app->make(PermissionProvider::class),
            $app->make(DecisionCache::class),
            $app->make(SecurityConfig::class)->denialStrategy,
        ));
        $this->app->scoped(AuthorizationEngine::class);
        $this->app->scoped(RoleResolver::class, static function (Application $app): RoleResolver {
            $provider = $app->make(SecurityConfig::class)->roleProvider;
            $instance = match ($provider) {
                null => null,
                'spatie' => new SpatieRoleProvider,
                default => $app->make($provider),
            };

            return new RoleResolver($instance instanceof RoleProvider ? $instance : null);
        });
        $this->app->scoped(TenantAuthorizer::class);
        $this->app->scoped(DefinitionAuthorizer::class, static fn (Application $app): DefinitionAuthorizer => new DefinitionAuthorizer(
            $app->make(AuthorizationEngine::class),
            $app->bound(RegistryInterface::class) ? $app->make(RegistryInterface::class) : null,
        ));
        $this->app->scoped(RecordAuthorizer::class);
        $this->app->scoped(FieldAuthorizer::class);
        $this->app->scoped(OperationAuthorizer::class, static fn (Application $app): OperationAuthorizer => new OperationAuthorizer(
            $app->make(AuthorizationEngine::class),
            $app->make(DefinitionAuthorizer::class),
            $app->make(ContextFactory::class),
            $app->make(TenantResolver::class),
            $app->make('auth'),
            $app->make(AuditReporter::class),
            $app->make(SecurityConfig::class),
            $app->bound(RegistryInterface::class) ? $app->make(RegistryInterface::class) : null,
        ));
        $this->app->scoped(SecurityInspector::class, static fn (Application $app): SecurityInspector => new SecurityInspector(
            $app,
            $app->make(SecurityConfig::class),
            $app->make(Gate::class),
            $app->bound(RegistryInterface::class) ? $app->make(RegistryInterface::class) : null,
        ));
        $this->app->singleton(SecurityManager::class);

        // Replace the framework defaults, never an application's own binding.
        $this->app->extend(RenderAuthorizer::class, static fn (RenderAuthorizer $authorizer, Application $app): RenderAuthorizer => $authorizer instanceof GateRenderAuthorizer ? new SecurityRenderAuthorizer($app) : $authorizer);
        $this->app->extend(DataAuthorizer::class, static fn (DataAuthorizer $authorizer, Application $app): DataAuthorizer => $authorizer instanceof GateDataAuthorizer ? new SecurityDataAuthorizer($app) : $authorizer);
        $this->app->extend(ScopeRegistry::class, static function (ScopeRegistry $scopes, Application $app): ScopeRegistry {
            $scopes->register(new SecurityDataScope($app));

            return $scopes;
        });
    }

    public function boot(): void
    {
        // Validate the configuration now (pure config: no user, no database), so unsafe settings fail at boot.
        $this->app->make(SecurityConfig::class);

        $this->publishes([dirname(__DIR__).'/config/gcego-security.php' => $this->app->configPath('gcego-security.php')], 'gcego-security-config');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware(AuthorizeDefinition::Alias, AuthorizeDefinition::class);
        $router->aliasMiddleware(ResolveTenant::Alias, ResolveTenant::class);

        // Livewire update requests re-run the tenant middleware of the page that mounted the component.
        if (class_exists(Livewire::class)) {
            Livewire::addPersistentMiddleware([ResolveTenant::class]);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/security',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/data'],
            capabilities: ['security', 'security.authorization', 'security.tenancy'],
            description: 'Deny-by-default authorization over Laravel Gates and Policies, tenant isolation, record and field access, and mandatory data scopes.',
        );
    }
}
