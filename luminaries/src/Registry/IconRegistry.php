<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Registry;

use Gcego\Luminaries\Contracts\IconProvider;
use Gcego\Luminaries\Exceptions\DuplicateRegistrationException;
use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\IconName;

/**
 * Registered providers and semantic aliases.
 *
 * Deterministic: providers keep registration order and aliases are plain
 * string maps, so the same configuration always yields the same registry.
 * Registration happens while the application boots; nothing here depends on
 * the current request.
 */
final class IconRegistry
{
    public const int MaxAliasDepth = 8;

    /**
     * @var array<string, IconProvider>
     */
    private array $providers = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Where each alias came from (a preset name, "config" or "runtime").
     *
     * @var array<string, string>
     */
    private array $origins = [];

    private int $version = 0;

    public function addProvider(IconProvider $provider): void
    {
        $name = $provider->name();

        if (preg_match(IconName::ProviderPattern, $name) !== 1) {
            throw InvalidConfigurationException::invalid('providers', sprintf('"%s" is not a valid provider name (lowercase letters, digits and dashes).', mb_substr($name, 0, 64)));
        }

        if (isset($this->providers[$name])) {
            throw DuplicateRegistrationException::provider($name);
        }

        $this->providers[$name] = $provider;
        $this->version++;
    }

    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function provider(string $name): ?IconProvider
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return array<string, IconProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * Map a semantic alias to a canonical identifier, a bare name or another alias.
     *
     * @throws DuplicateRegistrationException when the alias exists with another target and $override is false
     * @throws InvalidConfigurationException when the alias or target is malformed or would create a cycle
     */
    public function alias(string $alias, string $target, bool $override = false, string $origin = 'runtime'): void
    {
        if (preg_match(IconName::AliasPattern, $alias) !== 1) {
            throw InvalidConfigurationException::invalid('aliases', sprintf('"%s" is not a valid alias; use dotted lowercase segments such as "actions.edit".', mb_substr($alias, 0, 64)));
        }

        if (! IconName::isValid($target)) {
            throw InvalidConfigurationException::invalid('aliases', sprintf('the target of "%s" ("%s") is not a valid icon identifier.', $alias, mb_substr($target, 0, 96)));
        }

        if (isset($this->aliases[$alias]) && $this->aliases[$alias] !== $target && ! $override) {
            throw DuplicateRegistrationException::alias($alias, $this->aliases[$alias]);
        }

        $previous = $this->aliases[$alias] ?? null;
        $this->aliases[$alias] = $target;

        try {
            $this->chain($alias);
        } catch (InvalidConfigurationException $exception) {
            if ($previous === null) {
                unset($this->aliases[$alias]);
            } else {
                $this->aliases[$alias] = $previous;
            }

            throw $exception;
        }

        $this->origins[$alias] = $origin;
        $this->version++;
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    public function target(string $alias): ?string
    {
        return $this->aliases[$alias] ?? null;
    }

    public function origin(string $alias): ?string
    {
        return $this->origins[$alias] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function aliases(): array
    {
        $aliases = $this->aliases;
        ksort($aliases);

        return $aliases;
    }

    /**
     * Follow an alias to its first non-alias target.
     *
     * @return array{chain: list<string>, target: ?string} The aliases followed and the final target (null when an alias in the chain is undefined).
     *
     * @throws InvalidConfigurationException on cycles or chains deeper than MaxAliasDepth
     */
    public function chain(string $alias): array
    {
        $chain = [];
        $current = $alias;

        while (IconName::isValid($current) && preg_match(IconName::AliasPattern, $current) === 1) {
            if (in_array($current, $chain, true)) {
                throw InvalidConfigurationException::invalid('aliases', sprintf('alias cycle detected: %s -> %s.', implode(' -> ', $chain), $current));
            }

            if (count($chain) >= self::MaxAliasDepth) {
                throw InvalidConfigurationException::invalid('aliases', sprintf('alias chain from "%s" is deeper than %d.', $alias, self::MaxAliasDepth));
            }

            $chain[] = $current;

            if (! isset($this->aliases[$current])) {
                return ['chain' => $chain, 'target' => null];
            }

            $current = $this->aliases[$current];
        }

        return ['chain' => $chain, 'target' => $current];
    }

    /**
     * Incremented on every registration; lets resolvers invalidate memoized results.
     */
    public function version(): int
    {
        return $this->version;
    }
}
