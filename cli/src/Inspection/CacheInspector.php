<?php

declare(strict_types=1);

namespace Gcego\Cli\Inspection;

use Composer\InstalledVersions;
use Gcego\Registry\RegistryManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Foundation\CachesRoutes;

/**
 * gcego:cache-status: the caches GCEGO relies on, from each owner's public
 * API (gcego/registry's cache status, Laravel's configuration/route/event
 * caches) and, for packages without a status API (settings, analytics,
 * widgets), the store they use and how to clear it. Nothing is cleared.
 */
final readonly class CacheInspector
{
    public function __construct(private Application $app, private Repository $config, private RegistryManager $registry) {}

    /**
     * @return list<array{cache: string, status: string, details: string, clear: string|null}>
     */
    public function report(): array
    {
        $status = $this->registry->status();
        $rows = [[
            'cache' => 'gcego/registry definitions',
            'status' => match (true) {
                ! $status->enabled => 'disabled',
                ! $status->exists => 'missing',
                ! $status->valid => 'invalid',
                $status->fresh === false => 'stale',
                default => 'cached',
            },
            'details' => $status->exists ? sprintf('%s definitions%s%s', $status->definitions ?? '?', $status->generatedAt === null ? '' : ', built '.$status->generatedAt, $status->problem === null ? '' : ' ('.$status->problem.')') : 'built on first use',
            'clear' => 'php artisan gcego:registry-clear (rebuild: gcego:registry-cache)',
        ]];

        $rows[] = ['cache' => 'Laravel configuration', 'status' => $this->app instanceof CachesConfiguration && $this->app->configurationIsCached() ? 'cached' : 'not cached', 'details' => 'bootstrap/cache/config.php', 'clear' => 'php artisan config:clear'];
        $rows[] = ['cache' => 'Laravel routes (panel routes)', 'status' => $this->app instanceof CachesRoutes && $this->app->routesAreCached() ? 'cached' : 'not cached', 'details' => 'bootstrap/cache/routes-v7.php', 'clear' => 'php artisan route:clear'];
        $rows[] = ['cache' => 'Laravel events', 'status' => method_exists($this->app, 'eventsAreCached') && $this->app->eventsAreCached() ? 'cached' : 'not cached', 'details' => 'bootstrap/cache/events.php', 'clear' => 'php artisan event:clear'];
        $store = $this->config->get('cache.default');
        $store = is_string($store) ? $store : '';
        $driver = $this->config->get('cache.stores.'.$store.'.driver');
        $driver = is_string($driver) ? $driver : '';

        foreach (['gcego/settings' => ['settings values', 'php artisan gcego:settings:clear-cache'], 'gcego/analytics' => ['analytics results', 'AnalyticsManager::flush()'], 'gcego/widgets' => ['widget results', 'WidgetManager::forget($identity)']] as $package => [$label, $clear]) {
            if ($this->installed($package)) {
                $rows[] = ['cache' => $package.' '.$label, 'status' => 'store "'.$store.'"', 'details' => sprintf('%s driver; entries expire by their TTL', $driver), 'clear' => $clear];
            }
        }

        return $rows;
    }

    private function installed(string $package): bool
    {
        try {
            return InstalledVersions::isInstalled($package);
        } catch (\Throwable) {
            return false;
        }
    }
}
