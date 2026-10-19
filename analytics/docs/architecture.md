# Architecture

```
Definitions (components, registry kinds)          Dataset · Metric · Report · Dashboard
        │ validated on registration; references checked by AnalyticsDefinitionValidator
        ▼
AnalyticsManager ── MetricHandle / ReportHandle / dashboard()
        │                    (context: signed-in user + verified tenant, or as($user, $tenant))
        ▼
MetricCalculator · ReportManager · DashboardResolver      ← authorize (AnalyticsAuthorizer, deny by default)
        │ AnalyticsCache (definition hash · audience · request · dataset versions)
        ▼
AnalyticsQueryPlanner  — whitelist + limits → QueryPlan
        ▼
EloquentAnalyticsExecutor
   1. DataManager::scopedQuery(Source(model)) as the viewer: policy viewAny, global scopes, soft deletes, tenancy
   2. Dataset AnalyticsScope (record level)
   3. filters + period (bindings)
   4. SELECT dims, bucket, aggregates, COUNT(*) GROUP BY … LIMIT n+1   (+ count of groups)
   5. small-group suppression
        ▼
Results (MetricResult, ReportResult, DashboardView, ChartView) → Livewire views / exports / widgets
```

## Responsibilities

| Class | Responsibility |
| --- | --- |
| `AnalyticsManager` | The viewer's context; resolves definitions; delegates to the services below. |
| `MetricCalculator` | Values, series (gap filled), groups (top N plus "Other"), comparisons and composite metrics. |
| `ReportManager` | Filters, sorting, pagination, labels and visible columns. |
| `DashboardResolver` | Per-part authorization and calculation. |
| `AnalyticsQueryPlanner` | The only door to the executor. |
| `EloquentAnalyticsExecutor` | Aggregation in SQL. |
| `ExportManager`, `ReportScheduleManager` | Files, jobs and deliveries. |

Other packages do the rest:

| Package | Role |
| --- | --- |
| gcego/data | Rows the viewer may see |
| gcego/security | Requirements, tenants, `actingAs` |
| gcego/widgets | Chart rendering |
| gcego/notifications | Messages |
| Laravel | Queue, scheduler, cache, filesystem |

## Context and long-running workers

`AnalyticsContext` is immutable: user, tenant, time zone and correlation id. Services are scoped (reset per request and per queued job). Memoized authorization never outlives a request or job, and nothing is cached in static state.
