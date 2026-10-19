<?php

declare(strict_types=1);

namespace Gcego\Settings;

use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Settings\Cache\SettingsCache;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Diagnostics\SettingsInspector;
use Gcego\Settings\Resolution\ResolvedSetting;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Writing\SettingsWriteResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * The entry point of gcego/settings (no facade; inject it or app(SettingsManager::class)):
 *
 *     $settings->get('site_name');                                   // the request's own context
 *     $settings->set(key: 'site_name', value: 'GCEGO Learning Platform');
 *     $settings->forTenant($institution)->get('school_name');
 *     $settings->forUser($user)->get('preferred_theme');
 *
 * Without for*(), reads use the request's context: the signed-in user's
 * preference, else the verified tenant's value, else the panel's, else the
 * global value, else the default (each setting's own precedence, limited
 * to the scopes it allows). Writes without for*() go to the most specific
 * level of that context the setting allows (a user's preference, else the
 * verified tenant, else the panel, else global); name the level you mean
 * with global(), forPanel(), forTenant() or forUser() when it matters.
 *
 * The heavy lifting lives in small collaborators: SettingsReader
 * (resolution), SettingsWriter (authorized, validated, atomic writes),
 * ScopeResolver, SettingsCache, SettingAccessPolicy and SettingsValidator.
 * Resolved per request or job, so a context never outlives it.
 */
final readonly class SettingsManager
{
    public function __construct(private Container $container) {}

    public function scoped(): ScopedSettings
    {
        return new ScopedSettings($this->container, $this->container->make(ScopeResolver::class)->current(), automatic: true);
    }

    public function has(string $key): bool
    {
        return $this->scoped()->has($key);
    }

    public function get(string $key): mixed
    {
        return $this->scoped()->get($key);
    }

    public function resolve(string $key): ResolvedSetting
    {
        return $this->scoped()->resolve($key);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        return $this->scoped()->many($keys);
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return $this->scoped()->group($group);
    }

    public function set(string $key, mixed $value, ?int $expectedVersion = null): SettingsWriteResult
    {
        return $this->scoped()->set($key, $value, $expectedVersion);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $expectedVersions
     */
    public function setMany(array $values, array $expectedVersions = [], bool $partial = false): SettingsWriteResult
    {
        return $this->scoped()->setMany($values, $expectedVersions, $partial);
    }

    /**
     * @param  string|list<string>  $keys
     * @param  array<string, int>  $expectedVersions
     */
    public function reset(string|array $keys, array $expectedVersions = []): SettingsWriteResult
    {
        return $this->scoped()->reset($keys, $expectedVersions);
    }

    public function rotate(string $key, mixed $secret, ?int $expectedVersion = null): SettingsWriteResult
    {
        return $this->scoped()->rotate($key, $secret, $expectedVersion);
    }

    public function global(): ScopedSettings
    {
        return $this->scoped()->global();
    }

    public function forPanel(string $panel): ScopedSettings
    {
        return $this->scoped()->forPanel($panel);
    }

    public function forTenant(Tenant|Model|string|int $tenant): ScopedSettings
    {
        return $this->scoped()->forTenant($tenant);
    }

    public function forUser(Authenticatable $user): ScopedSettings
    {
        return $this->scoped()->forUser($user);
    }

    public function actingAs(Authenticatable $user): ScopedSettings
    {
        return $this->scoped()->actingAs($user);
    }

    public function asSystem(string $name = 'system'): ScopedSettings
    {
        return $this->scoped()->asSystem($name);
    }

    public function definition(string $key): SettingDefinition
    {
        return $this->definitions()->definition($key);
    }

    public function definitions(): SettingsDefinitions
    {
        return $this->container->make(SettingsDefinitions::class);
    }

    /**
     * Register a group or page at runtime (tests, packages); discovery is the usual way.
     */
    public function register(SettingsGroup|SettingsPage $definition, ?string $scope = null): DefinitionDescriptor
    {
        return $this->definitions()->register($definition, $scope);
    }

    /**
     * Invalidate every cached value on every worker (writes already invalidate what they change).
     */
    public function flushCache(): void
    {
        $this->container->make(SettingsCache::class)->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(bool $runtime = true): array
    {
        return $this->container->make(SettingsInspector::class)->report($runtime);
    }
}
