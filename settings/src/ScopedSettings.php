<?php

declare(strict_types=1);

namespace Gcego\Settings;

use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\MediaManager;
use Gcego\Media\Models\MediaRecord;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Exceptions\SettingScopeException;
use Gcego\Settings\Resolution\ResolvedSetting;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\Types\SettingType;
use Gcego\Settings\Writing\SettingsWriter;
use Gcego\Settings\Writing\SettingsWriteResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Settings seen from one scope context, and written as one actor:
 *
 *     $settings->forTenant($institution)->get('school_name');
 *     $settings->forUser($user)->set('preferred_theme', 'dark');
 *     $settings->global()->asSystem('seeder')->set('site_name', 'GCEGO');
 *
 * Reads are for trusted server code and return typed effective values
 * (sensitive ones decrypted: never pass them to a view or a response).
 * Writes go to the level named with global(), forPanel(), forTenant() or
 * forUser(); on the request's own context (SettingsManager without for*()),
 * to the most specific known level the setting allows.
 * Writes always authorize the actor: the signed-in user unless actingAs()
 * or asSystem() chose another, and refuse when nobody is signed in.
 * Immutable: every for*() and as*() returns a new instance.
 */
final readonly class ScopedSettings
{
    public function __construct(
        private Container $container,
        private ScopeContext $context,
        private ?SettingsActor $actor = null,
        private bool $automatic = false,
    ) {}

    public function context(): ScopeContext
    {
        return $this->context;
    }

    /**
     * Whether a setting with this key is registered.
     */
    public function has(string $key): bool
    {
        return $this->definitions()->has($key);
    }

    /**
     * The typed effective value (the most specific stored value, else the default).
     */
    public function get(string $key): mixed
    {
        return $this->resolve($key)->value;
    }

    /**
     * The effective value with where it came from and the target's version.
     */
    public function resolve(string $key): ResolvedSetting
    {
        return $this->reader()->resolve($this->definitions()->definition($key), $this->context);
    }

    /**
     * Several typed values in one round trip.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $definitions = $this->definitions();
        $resolved = $this->reader()->resolveMany(array_map($definitions->definition(...), array_values($keys)), $this->context);

        return array_map(static fn (ResolvedSetting $setting): mixed => $setting->value, $resolved);
    }

    /**
     * Every typed value of a group.
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $resolved = $this->reader()->resolveMany($this->definitions()->inGroup($group), $this->context);

        return array_map(static fn (ResolvedSetting $setting): mixed => $setting->value, $resolved);
    }

    /**
     * The gcego/media file a Media setting points to, through gcego/media's
     * tenant-scoped lookup and view authorization (null when unset or not allowed).
     */
    public function media(string $key): ?MediaRecord
    {
        $definition = $this->definitions()->definition($key);
        $value = $this->get($key);

        if ($definition->type !== SettingType::Media || ! is_string($value) || ! $this->container->bound(MediaManager::class)) {
            return null;
        }

        try {
            $media = $this->container->make(MediaManager::class);
            $record = $media->find($value);

            return $media->allows(MediaAbility::View, $record) ? $record : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Store an override at the target scope.
     */
    public function set(string $key, mixed $value, ?int $expectedVersion = null): SettingsWriteResult
    {
        return $this->writer()->write($this->target([$key]), [$key => $value], $this->writingActor(), $expectedVersion === null ? [] : [$key => $expectedVersion]);
    }

    /**
     * Store several overrides atomically: all of them, or none (unless $partial).
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $expectedVersions
     */
    public function setMany(array $values, array $expectedVersions = [], bool $partial = false): SettingsWriteResult
    {
        return $this->writer()->write($this->target(array_keys($values)), $values, $this->writingActor(), $expectedVersions, partial: $partial);
    }

    /**
     * Remove overrides at the target scope, so the settings inherit again.
     *
     * @param  string|list<string>  $keys
     * @param  array<string, int>  $expectedVersions
     */
    public function reset(string|array $keys, array $expectedVersions = []): SettingsWriteResult
    {
        $keys = is_string($keys) ? [$keys] : array_values($keys);

        return $this->writer()->reset($this->target($keys), $keys, $this->writingActor(), $expectedVersions);
    }

    /**
     * Replace a sensitive value (history and events record that it changed, never the value).
     */
    public function rotate(string $key, mixed $secret, ?int $expectedVersion = null): SettingsWriteResult
    {
        return $this->writer()->rotate($this->target([$key]), $key, $secret, $this->writingActor(), $expectedVersion);
    }

    public function global(): self
    {
        return new self($this->container, $this->scopes()->global(), $this->actor);
    }

    public function forPanel(string $panel): self
    {
        return new self($this->container, $this->scopes()->forPanel($panel), $this->actor);
    }

    public function forTenant(Tenant|Model|string|int $tenant): self
    {
        return new self($this->container, $this->scopes()->forTenant($tenant), $this->actor);
    }

    public function forUser(Authenticatable $user): self
    {
        return new self($this->container, $this->scopes()->forUser($user), $this->actor);
    }

    /**
     * Write as this person (authorized like the signed-in user would be).
     */
    public function actingAs(Authenticatable $user): self
    {
        return new self($this->container, $this->context, SettingsActor::user($user), $this->automatic);
    }

    /**
     * Write as trusted system code (seeders, deployments, commands): no
     * person authorization, but validation, scope rules and history as always.
     */
    public function asSystem(string $name = 'system'): self
    {
        return new self($this->container, $this->context, SettingsActor::system($name), $this->automatic);
    }

    /**
     * Where writes go: the named level (global(), forPanel(), forTenant(),
     * forUser()), or, for the request's own context, the most specific
     * known level each setting allows; all keys of one write must agree.
     *
     * @param  list<array-key>  $keys
     */
    private function target(array $keys): ScopeContext
    {
        if (! $this->automatic) {
            return $this->context;
        }

        $targets = [];

        foreach ($keys as $key) {
            $definition = $this->definitions()->definition((string) $key);

            foreach ([SettingScope::User, SettingScope::Tenant, SettingScope::Panel, SettingScope::Global] as $scope) {
                if ($definition->allows($scope) && $this->context->at($scope)->targetKey() !== null && $this->context->includes($scope)) {
                    $targets[$scope->value] = $scope;

                    break;
                }
            }
        }

        if (count($targets) > 1) {
            throw SettingScopeException::because('These settings are stored at different scopes here; name the scope with global(), forPanel(), forTenant() or forUser().');
        }

        return $targets === [] ? $this->context : $this->context->at(array_values($targets)[0]);
    }

    private function writingActor(): SettingsActor
    {
        if ($this->actor !== null) {
            return $this->actor;
        }

        $user = $this->container->make(AuthFactory::class)->guard($this->container->make(SecurityConfig::class)->guard)->user();

        return $user instanceof Authenticatable ? SettingsActor::user($user) : throw SettingAuthorizationException::for('change (sign in, or use asSystem() in trusted code)');
    }

    private function definitions(): SettingsDefinitions
    {
        return $this->container->make(SettingsDefinitions::class);
    }

    private function reader(): SettingsReader
    {
        return $this->container->make(SettingsReader::class);
    }

    private function writer(): SettingsWriter
    {
        return $this->container->make(SettingsWriter::class);
    }

    private function scopes(): ScopeResolver
    {
        return $this->container->make(ScopeResolver::class);
    }
}
