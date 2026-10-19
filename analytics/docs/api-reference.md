# API reference

## Definitions

| Class | Named arguments | Children |
| --- | --- | --- |
| `Datasets\Dataset` | `name`, `label`, `description`, `model`, `provider`, `dateColumn`, `minimumGroupSize`, `scope` | `Field`, `Dimension`, requirements |
| `Datasets\Field` | `name`, `label`, `type` (number, integer, string, boolean, date, datetime), `aggregates`, `sensitive`, `filterable` | requirements |
| `Dimensions\Dimension` | `name`, `label`, `column`, `type` (category, boolean, status), `options`, `labelModel`, `labelColumn`, `labels`, `filterable` | requirements |
| `Metrics\Metric` | `name`, `label`, `description`, `dataset`, `model`, `aggregate`, `field`, `filters`, `dateColumn`, `interval`, `format`, `unit`, `precision`, `currency`, `numerator`, `denominator`, `formula`, `per`, `cache`, `fill` | requirements |
| `Reports\Report` | `name`, `title`, `description`, `dataset`, `dateColumn`, `defaultRange`, `perPage`, `sort`, `direction`, `export`, `formats`, `schedulable`, `exportAbility` | `ReportColumn`, `ReportFilter`, requirements |
| `Reports\ReportColumn` | `name`, `label`, `dimension`, `aggregate`, `field`, `format`, `precision`, `currency`, `unit`, `sortable`, `drillDown` | — |
| `Reports\ReportFilter` | `name`, `label`, `multiple`, `operator` (eq, gte, lte), `default`, `required` | — |
| `Dashboard` | `name`, `title`, `description`, `defaultRange`, `comparison`, `refresh` | `Metric`, `Kpi`, `Chart`, `ReportSection`, `ReportFilter`, requirements |
| `Dashboards\Chart` | `name`, `label`, `metric`, `type`, `interval`, `groupBy`, `compare`, `limit` | — |

## Services

| Service | Methods |
| --- | --- |
| `AnalyticsManager` | `metric($name): MetricHandle`, `report($name): ReportHandle`, `dashboard($name, ?DashboardState): DashboardView`, `query(AnalyticsQuery): AnalyticsRows`, `schema($dataset): DatasetSchema`, `flush(?$dataset)`, `as($user, $tenant, ?$timezone)`, `context()` |
| `MetricHandle` | `calculate(range:, comparison:, filters:, interval:, groupBy:, limit:, compareSeries:): MetricResult` |
| `ReportHandle` | `run(filters:, range:, sort:, direction:, page:, perPage:): ReportResult` |
| `Exports\ExportManager` | `request(Report, ReportRequest, $format, AnalyticsContext, $recipients, $schedule, $queue)`, `generate(AnalyticsExport)`, `download($ulid, AnalyticsContext)`, `prune()` |
| `Scheduling\ReportScheduleManager` | `create(...)`, `cancel(...)`, `due()`, `deliver(ScheduledReport)`, `nextRun(...)` |
| `Diagnostics\AnalyticsInspector` | `report($runtime = true)` |

## Contracts

- `Contracts\AnalyticsScope::apply($query, AnalyticsContext)`
- `Contracts\AnalyticsProvider::execute(QueryPlan, AnalyticsContext): AnalyticsRows`
- `Contracts\DimensionLabels::labels(list $keys, AnalyticsContext): array`

## Exceptions

| Exception | Meaning |
| --- | --- |
| `AnalyticsQueryException` (422) | A query was rejected; carries a stable `reason`. |
| `AnalyticsAuthorizationException` | 403 |
| `AnalyticsNotFoundException` | 404 |
| `InvalidDefinitionException` | An invalid definition. |

`AnalyticsQueryException` reasons:

- **Columns and grouping:** `measure`, `field`, `aggregate`, `dimension`, `filter`, `sort`.
- **Time:** `date_column`, `date_range`, `interval`, `timezone`.
- **Size:** `limit`, `offset`.
- **Exports and schedules:** `format`, `export_limit`, `export_rows`, `schedule`, `recipient`.
- **Execution:** `timeout`, `driver`, `comparison`.

## Events

| Event | When |
| --- | --- |
| `MetricCalculated` | A metric is calculated. |
| `ReportExecuted` | A report runs. |
| `AnalyticsQueryRejected` | The planner rejects a query. |
| `AnalyticsCacheFlushed` | Cached results are invalidated. |
| `ExportStarted`, `ExportCompleted`, `ExportFailed` | An export changes state. |
| `ScheduledReportDelivered` | A scheduled run is delivered. |

Events carry identifiers, counts, durations, tenant and correlation id only.

## Commands and routes

- **Commands:** `gcego:analytics:inspect {--json} {--definitions}`, `gcego:analytics:run-schedules`, `gcego:analytics:prune-exports`.
- **Download route:** `gcego.analytics.exports.download` (signed).
- **Panel routes:** `<namespace>analytics.dashboard`, `<namespace>analytics.reports`, `<namespace>analytics.report`.
- **Livewire components:** `gcego-analytics-dashboard`, `gcego-analytics-report`.
