<?php

declare(strict_types=1);

namespace Gcego\Settings\Cache;

use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\SettingsConfig;

/**
 * Cache keys of settings. One entry holds every stored value of one scope
 * instance (global, a panel, a tenant, a user), so an effective value is
 * always assembled from current scope entries and updating a global value
 * immediately changes every inherited value.
 *
 * Keys are "<prefix>:<generation>:<scope>:<sha256 of the scope key>:<stamp>":
 * the scope key is hashed (no collisions between "a:b" and "a" + "b",
 * no tenant ids or user ids in cache key names), the generation changes on
 * flush, and the stamp changes on every write to that scope instance.
 */
final readonly class SettingsCacheKeyResolver
{
    public function __construct(private SettingsConfig $config) {}

    public function generation(): string
    {
        return $this->config->cachePrefix.':generation';
    }

    public function stamp(string $generation, SettingScope $scope, string $scopeKey): string
    {
        return $this->config->cachePrefix.':'.$generation.':stamp:'.$scope->value.':'.self::hash($scopeKey);
    }

    public function scope(string $generation, SettingScope $scope, string $scopeKey, string $stamp): string
    {
        return $this->config->cachePrefix.':'.$generation.':scope:'.$scope->value.':'.self::hash($scopeKey).':'.$stamp;
    }

    private static function hash(string $scopeKey): string
    {
        return hash('sha256', $scopeKey);
    }
}
