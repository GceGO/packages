<?php

declare(strict_types=1);

namespace Gcego\Analytics\Cache;

use Closure;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Events\AnalyticsCacheFlushed;
use Gcego\Analytics\Security\AnalyticsContext;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Cached analytics results. Keys combine:
 *
 * - the kind and name, and a fingerprint of the definition (changing a
 *   definition never serves old results);
 * - the audience: the user and the tenant (results computed under one
 *   authorization context are never served to another);
 * - the request: period, comparison, filters, grouping, interval, page;
 * - the version of every dataset involved (flush() bumps it).
 *
 * Values are plain arrays. Nothing is held in PHP memory between requests.
 */
final readonly class AnalyticsCache
{
    public const string Prefix = 'gcego-analytics:';

    public function __construct(private Factory $caches, private AnalyticsConfig $config, private Dispatcher $events) {}

    /**
     * @template T of array<array-key, mixed>
     *
     * @param  list<string>  $datasets
     * @param  Closure(): T  $compute
     * @return array{0: T, 1: bool} The value and whether it came from the cache.
     */
    public function remember(string $kind, string $name, string $definitionHash, array $datasets, string $request, AnalyticsContext $context, ?int $ttl, Closure $compute): array
    {
        $ttl ??= $this->config->cacheTtl;

        if (! $this->config->cache || $ttl <= 0) {
            return [$compute(), false];
        }

        $key = $this->key($kind, $name, $definitionHash, $datasets, $request, $context);
        $store = $this->store();
        $cached = $store->get($key);

        if (is_array($cached) && array_key_exists('value', $cached) && is_array($cached['value'])) {
            /** @var T $value */
            $value = $cached['value'];

            return [$value, true];
        }

        $value = $compute();
        $store->put($key, ['value' => $value], $ttl);

        return [$value, false];
    }

    /**
     * @param  list<string>  $datasets
     */
    public function key(string $kind, string $name, string $definitionHash, array $datasets, string $request, AnalyticsContext $context): string
    {
        $versions = array_map(fn (string $dataset): string => $dataset.'@'.$this->version($dataset), $datasets);
        sort($versions);

        return self::Prefix.$kind.':'.$name.':'.hash('sha256', implode('|', [$definitionHash, $context->audience(), $context->timezone, implode(',', $versions), $request, (string) $this->version('*'), (string) $this->version('audience:'.$context->audience())]));
    }

    /**
     * Invalidate cached results of one dataset (or of every dataset with null).
     */
    public function flush(?string $dataset = null): void
    {
        $key = self::Prefix.'version:'.($dataset ?? '*');
        $store = $this->store();
        $store->put($key, $this->version($dataset ?? '*') + 1);
        $this->events->dispatch(new AnalyticsCacheFlushed($dataset));
    }

    /**
     * Fresh results for one viewer only (a dashboard's "Refresh"), leaving everyone else's cache alone.
     */
    public function refresh(AnalyticsContext $context): void
    {
        $key = self::Prefix.'version:audience:'.$context->audience();
        $this->store()->put($key, $this->version('audience:'.$context->audience()) + 1, max(60, $this->config->cacheTtl));
    }

    private function version(string $dataset): int
    {
        $version = $this->store()->get(self::Prefix.'version:'.$dataset);

        return is_int($version) ? $version : 0;
    }

    private function store(): Repository
    {
        return $this->caches->store($this->config->cacheStore);
    }
}
