<?php

declare(strict_types=1);

namespace Gcego\Analytics;

use Gcego\Analytics\Console\InspectAnalyticsCommand;
use Gcego\Analytics\Console\PruneExportsCommand;
use Gcego\Analytics\Console\RunSchedulesCommand;
use Gcego\Analytics\Http\ExportDownloadController;
use Gcego\Analytics\Integration\AnalyticsNotifier;
use Gcego\Analytics\Integration\PanelAnalyticsExtension;
use Gcego\Analytics\Integration\Workflows\WorkflowAnalytics;
use Gcego\Analytics\Livewire\AnalyticsDashboardHost;
use Gcego\Analytics\Livewire\ReportViewer;
use Gcego\Analytics\Registry\AnalyticsKind;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Notifications\Definitions\NotificationCategory;
use Gcego\Notifications\NotificationManager;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Gcego\Workflows\WorkflowsServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/analytics; also the "gcego/analytics" GCEGO package.
 *
 * register(): configuration, services, the registry kinds "dataset",
 * "metric", "report" and "analytics-dashboard" and their discovery roots
 * (app/GCEGo/Analytics/{Datasets,Metrics,Reports,Dashboards}); no queries.
 * boot(): views, translations, migrations, Livewire components
 * (gcego-analytics-dashboard, gcego-analytics-report), the signed export
 * download route, styling slots, the "reports" notification category, the
 * panel extension, the optional workflow analytics, scheduler entries and
 * console commands.
 */
final class AnalyticsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-analytics';

    /** Styling slots of analytics views, on Dashboard::class. */
    public const array Slots = [
        'wrapper' => 'The page.', 'header' => 'Headers.', 'toolbar' => 'Period, comparison and actions.', 'filters' => 'Filter controls.',
        'metric' => 'A KPI card.', 'metric_value' => 'A KPI value.', 'metric_trend' => 'A KPI comparison.', 'chart' => 'A chart card.',
        'chart_legend' => 'A chart legend.', 'report' => 'A report.', 'report_table' => 'A report table.', 'comparison' => 'Comparison text.',
        'empty' => 'Empty states.', 'loading' => 'Loading states.', 'error' => 'Error states.',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-analytics.php', self::Name);

        $this->app->singleton(AnalyticsConfig::class, static function (Application $app): AnalyticsConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return AnalyticsConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(Support\AnalyticsStyles::class);

        // Per request (or job): they read the current user, tenant and authorization state.
        foreach ([
            Registry\AnalyticsDefinitions::class, Definitions\AnalyticsDefinitionValidator::class, Security\AnalyticsAuthorizer::class, Security\ContextScope::class,
            Queries\AnalyticsQueryPlanner::class, Queries\EloquentAnalyticsExecutor::class, Dimensions\DimensionLabelResolver::class,
            Cache\AnalyticsCache::class, Metrics\MetricCalculator::class, Reports\ReportManager::class, Dashboards\DashboardResolver::class,
            Integration\ChartBuilder::class, AnalyticsManager::class, Exports\ExportManager::class, Scheduling\ReportScheduleManager::class,
            AnalyticsNotifier::class, Diagnostics\AnalyticsInspector::class,
        ] as $scoped) {
            $this->app->scoped($scoped);
        }

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static function (DefinitionKinds $kinds): void {
            foreach (array_keys(AnalyticsKind::Kinds) as $kind) {
                $kinds->register(new AnalyticsKind($kind));
            }
        });

        // Configuration is final by the time this is first resolved (Testbench sets it after register()).
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->addDiscoveryRoots());
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Name);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->loadMigrationsFrom($root.'/database/migrations');
        $this->publishes([$root.'/config/gcego-analytics.php' => $this->app->configPath('gcego-analytics.php')], 'gcego-analytics-config');
        $this->publishes([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-analytics-migrations');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/gcego-analytics')], 'gcego-analytics-views');

        Livewire::component('gcego-analytics-dashboard', AnalyticsDashboardHost::class);
        Livewire::component('gcego-analytics-report', ReportViewer::class);

        $slots = [];

        foreach (self::Slots as $slot => $description) {
            $slots[] = new SlotDefinition($slot, description: $description);
        }

        $this->app->make(ChromaManager::class)->defineSlots(Dashboard::class, $slots, inherit: true);

        if ($this->app->bound(NotificationManager::class)) {
            $this->app->make(NotificationManager::class)->category(new NotificationCategory(AnalyticsNotifier::Category, 'gcego-analytics::messages.category'));
        }

        if ($this->app->bound(PanelManager::class)) {
            $this->app->make(PanelManager::class)->extend(PanelAnalyticsExtension::class);
        }

        $this->registerRoutes();

        $this->app->booted(function (): void {
            $config = $this->app->make(AnalyticsConfig::class);

            if ($config->workflows && class_exists(WorkflowsServiceProvider::class) && $this->app->providerIsLoaded(WorkflowsServiceProvider::class)) {
                WorkflowAnalytics::register($this->app->make(RegistryInterface::class));
            }
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $config = $this->app->make(AnalyticsConfig::class);

            if ($config->schedules && $config->scheduleCommand) {
                $schedule->command(RunSchedulesCommand::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
                $schedule->command(PruneExportsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([InspectAnalyticsCommand::class, RunSchedulesCommand::class, PruneExportsCommand::class]);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/analytics',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/widgets', 'gcego/pages', 'gcego/panels', 'gcego/notifications'],
            capabilities: ['analytics', 'analytics.metrics', 'analytics.reports', 'analytics.dashboards', 'analytics.exports', 'analytics.schedules'],
            description: 'Declarative metrics, datasets, reports and dashboards with authorized, tenant-scoped aggregation, exports and scheduled reports.',
        );
    }

    private function registerRoutes(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $this->app->booted(function (): void {
            $router = $this->app->make(Router::class);
            $router->middleware(['web', 'auth', 'gcego.tenant:optional', 'signed'])
                ->get('analytics/exports/{export}', ExportDownloadController::class)
                ->where('export', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')
                ->name('gcego.analytics.exports.download');
            $router->getRoutes()->refreshNameLookups();
        });
    }

    private function addDiscoveryRoots(): null
    {
        $config = $this->app->make(Repository::class);

        if ($config->get(self::Name.'.discovery.enabled', true) !== true) {
            return null;
        }

        $paths = $config->get(self::Name.'.discovery.paths', []);
        $roots = $config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];

        foreach (is_array($paths) ? $paths : [] as $path) {
            if (! is_string($path)) {
                continue;
            }

            foreach (AnalyticsKind::Kinds as $kind => [, $folder]) {
                $folderPath = rtrim($path, '/').'/'.$folder;

                foreach ($roots as $root) {
                    if (is_array($root) && ($root['kind'] ?? null) === $kind && ($root['path'] ?? null) === $folderPath) {
                        continue 2;
                    }
                }

                $roots[] = ['path' => $folderPath, 'kind' => $kind, 'entrypoint' => 'file'];
            }
        }

        $config->set('gcego-registry.discovery.roots', $roots);

        return null;
    }
}
