<?php

declare(strict_types=1);

namespace Gcego\Analytics\Diagnostics;

use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Definitions\AnalyticsDefinitionValidator;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Models\AnalyticsExport;
use Gcego\Analytics\Models\ScheduledReport;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Registry\AnalyticsKind;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Support\Keys;
use Illuminate\Contracts\Auth\Access\Gate;
use Throwable;

/**
 * What an operator needs to know (gcego:analytics:inspect): every
 * registered dataset, metric, report and dashboard with its problems
 * (unknown references, disallowed aggregates, cycles), definitions
 * without authorization (denied while security.require_authorization is
 * on), Eloquent datasets with no policy (denied) or no tenant scope
 * (warning), and the queue, cache, scheduler and export state. Names and
 * counts only — never data.
 */
final readonly class AnalyticsInspector
{
    public function __construct(
        private AnalyticsDefinitions $definitions,
        private AnalyticsDefinitionValidator $validator,
        private AnalyticsConfig $config,
        private Gate $gate,
    ) {}

    /**
     * @return array{definitions: array<string, list<array{name: string, problems: list<string>, warnings: list<string>}>>, runtime: array<string, mixed>, config: array<string, mixed>}
     */
    public function report(bool $runtime = true): array
    {
        $definitions = [];

        foreach (array_keys(AnalyticsKind::Kinds) as $kind) {
            foreach ($this->definitions->names($kind) as $name) {
                $definitions[$kind][] = $this->inspect($kind, $name);
            }
        }

        $tenancy = config('gcego-security.tenancy.models', []);

        return [
            'definitions' => $definitions,
            'runtime' => $runtime ? $this->runtime() : [],
            'config' => [
                'require_authorization' => $this->config->requireAuthorization,
                'enforce_tenant_scope' => $this->config->enforceTenantScope,
                'tenancy_models' => is_array($tenancy) ? count($tenancy) : 0,
                'cache' => $this->config->cache ? ($this->config->cacheStore ?? Keys::string(config('cache.default'))).' / '.$this->config->cacheTtl.'s' : 'off',
                'queue' => ($this->config->exportConnection ?? Keys::string(config('queue.default'))).' / '.($this->config->exportQueue ?? 'default'),
                'scheduler' => $this->config->schedules && $this->config->scheduleCommand ? 'gcego:analytics:run-schedules every five minutes' : 'off',
                'limits' => ['max_rows' => $this->config->maxRows, 'max_chart_points' => $this->config->maxChartPoints, 'max_group_cardinality' => $this->config->maxGroupCardinality, 'max_range_days' => $this->config->maxRangeDays, 'export_max_rows' => $this->config->exportMaxRows],
                'minimum_group_size' => $this->config->minimumGroupSize,
            ],
        ];
    }

    /**
     * @return array{name: string, problems: list<string>, warnings: list<string>}
     */
    private function inspect(string $kind, string $name): array
    {
        $problems = [];
        $warnings = [];

        try {
            $definition = match ($kind) {
                AnalyticsKind::Dataset => $this->definitions->dataset($name),
                AnalyticsKind::Metric => $this->definitions->metric($name),
                AnalyticsKind::Report => $this->definitions->report($name),
                default => $this->definitions->dashboard($name),
            };

            $problems = match (true) {
                $definition instanceof Metric => $this->validator->metric($definition),
                $definition instanceof Report => $this->validator->report($definition),
                $definition instanceof Dashboard => $this->validator->dashboard($definition),
                default => [],
            };

            $mandatory = $kind !== AnalyticsKind::Metric || ($definition instanceof Metric && $definition->model() !== null);

            if ($mandatory && $definition->requirements() === [] && $this->config->requireAuthorization) {
                $problems[] = sprintf('%s "%s" has no gcego/security requirement, so everyone is denied; add one (for example new Ability(...)).', $kind, $name);
            }

            if ($definition instanceof Dataset) {
                $warnings = $this->datasetWarnings($definition);
            }
        } catch (Throwable $exception) {
            $problems[] = $exception->getMessage();
        }

        return ['name' => $name, 'problems' => $problems, 'warnings' => $warnings];
    }

    /**
     * @return list<string>
     */
    private function datasetWarnings(Dataset $dataset): array
    {
        $model = $dataset->model();

        if ($model === null) {
            return ['a custom provider: it must apply the tenant and access rules itself.'];
        }

        $warnings = [];

        try {
            if ($this->gate->getPolicyFor($model) === null) {
                $warnings[] = sprintf('%s has no policy: gcego/data denies its rows to everyone.', $model);
            }
        } catch (Throwable) {
        }

        $tenancy = config('gcego-security.tenancy.models', []);
        $scoped = false;

        foreach (is_array($tenancy) ? $tenancy : [] as $target => $column) {
            if (is_string($target) && is_a($model, $target, true)) {
                $scoped = true;
            }
        }

        if (! $scoped && config('gcego-security.tenancy.enabled') === true) {
            $warnings[] = sprintf('%s has no tenant scope (gcego-security.tenancy.models): every tenant\'s rows are aggregated unless its policy or Scope restricts them.', $model);
        }

        return $warnings;
    }

    /**
     * @return array<string, mixed>
     */
    private function runtime(): array
    {
        try {
            return [
                'exports' => AnalyticsExport::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all(),
                'failed_exports_24h' => AnalyticsExport::query()->where('status', AnalyticsExport::Failed)->where('updated_at', '>=', now()->subDay())->count(),
                'active_schedules' => ScheduledReport::query()->where('active', true)->count(),
                'paused_schedules' => ScheduledReport::query()->where('active', false)->where('last_status', 'like', 'paused%')->count(),
                'overdue_schedules' => ScheduledReport::query()->where('active', true)->where('next_run_at', '<', now()->subMinutes(30))->count(),
            ];
        } catch (Throwable $exception) {
            return ['error' => 'Analytics tables are not available: '.class_basename($exception)];
        }
    }
}
