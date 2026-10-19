<?php

declare(strict_types=1);

namespace Gcego\Analytics;

use Gcego\Analytics\Cache\AnalyticsCache;
use Gcego\Analytics\Dashboards\DashboardResolver;
use Gcego\Analytics\Dashboards\DashboardState;
use Gcego\Analytics\Dashboards\DashboardView;
use Gcego\Analytics\Datasets\DatasetSchema;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Metrics\MetricCalculator;
use Gcego\Analytics\Metrics\MetricHandle;
use Gcego\Analytics\Queries\AnalyticsQuery;
use Gcego\Analytics\Queries\AnalyticsQueryPlanner;
use Gcego\Analytics\Queries\AnalyticsRows;
use Gcego\Analytics\Queries\EloquentAnalyticsExecutor;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportHandle;
use Gcego\Analytics\Reports\ReportManager;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * The entry point of gcego/analytics for the current viewer (the signed-in
 * user in the verified tenant) or an explicit context:
 *
 *     $analytics = app(AnalyticsManager::class);
 *     $analytics->metric('total_students')->calculate()->value;
 *     $analytics->report('student-performance')->run(filters: ['academic_year' => '2026']);
 *     $analytics->dashboard('academic-overview');
 *     $analytics->as($user, '12')->metric(...);   // scheduled work: an explicit, re-verified context
 *
 * It only resolves definitions and delegates: MetricCalculator,
 * ReportManager, DashboardResolver, the planner and executor do the work.
 */
final readonly class AnalyticsManager
{
    public function __construct(
        private AnalyticsDefinitions $definitions,
        private MetricCalculator $metrics,
        private ReportManager $reports,
        private DashboardResolver $dashboards,
        private AnalyticsQueryPlanner $planner,
        private EloquentAnalyticsExecutor $executor,
        private AnalyticsAuthorizer $authorizer,
        private AnalyticsCache $cache,
        private AnalyticsConfig $config,
        private TenantContext $tenants,
        private AuthFactory $auth,
        private ?AnalyticsContext $explicit = null,
    ) {}

    /**
     * The same API for another viewer and tenant (scheduled reports, jobs). The tenant is verified on use.
     */
    public function as(?Authenticatable $user, ?string $tenant, ?string $timezone = null): self
    {
        return new self($this->definitions, $this->metrics, $this->reports, $this->dashboards, $this->planner, $this->executor, $this->authorizer, $this->cache, $this->config, $this->tenants, $this->auth, new AnalyticsContext($user, $tenant, $timezone ?? $this->config->timezone()));
    }

    public function context(): AnalyticsContext
    {
        if ($this->explicit !== null) {
            return $this->explicit;
        }

        $user = $this->auth->guard()->user();
        $tenant = $this->tenants->tenant();

        return new AnalyticsContext($user, $tenant === null ? null : (string) $tenant->key, $this->config->timezone());
    }

    public function metric(string $name): MetricHandle
    {
        return new MetricHandle($this->definitions->metric($name), $this->metrics, $this->context());
    }

    public function report(string $name): ReportHandle
    {
        return new ReportHandle($this->definitions->report($name), $this->reports, $this->context());
    }

    /**
     * A dashboard for a state (default: its default period and comparison, no filters).
     */
    public function dashboard(string $name, ?DashboardState $state = null): DashboardView
    {
        $dashboard = $this->definitions->dashboard($name);
        $context = $this->context();

        return $this->dashboards->resolve($dashboard, $state ?? DashboardState::fromInput($dashboard, $context->timezone), $context);
    }

    /**
     * A custom query on a registered dataset (validated and authorized like any other).
     */
    public function query(AnalyticsQuery $query): AnalyticsRows
    {
        $context = $this->context();

        return $this->executor->execute($this->planner->plan($query, $context), $context);
    }

    /**
     * What the viewer may use of a dataset.
     */
    public function schema(string $dataset): DatasetSchema
    {
        $definition = $this->definitions->dataset($dataset);
        $context = $this->context();

        if (! $this->authorizer->dataset($definition, $context)) {
            throw AnalyticsAuthorizationException::for('use this dataset');
        }

        return DatasetSchema::for($definition, $this->authorizer, $context);
    }

    /**
     * Invalidate cached results (of one dataset, or all).
     */
    public function flush(?string $dataset = null): void
    {
        $this->cache->flush($dataset);
    }
}
