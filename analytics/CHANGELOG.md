# Changelog

## Unreleased

- **Changed (routes):** the panel report pages moved from `<panel>/reports` and `<panel>/reports/{report}` to `<panel>/analytics/reports` and `<panel>/analytics/reports/{report}`, next to the dashboards. `<panel>/reports` collided with any application page named "reports" (gcego/pages reported a route conflict). Route names are unchanged (`<namespace>analytics.reports`, `<namespace>analytics.report`), so links built from route names keep working; update hard-coded paths. A dashboard can no longer be named "reports".
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## 0.1.0 - 2026-10-18

First release.

- **Definitions** (registry kinds `dataset`, `metric`, `report`, `analytics-dashboard`, discovered from `app/GCEGo/Analytics/{Datasets,Metrics,Reports,Dashboards}`):
  - `Dataset`, `Field`, `Dimension`, `Metric`, `Report`, `ReportColumn`, `ReportFilter`, `Dashboard`, `Kpi`, `Chart` and `ReportSection`;
  - shorthand, named and `Property` syntax;
  - per-definition validation, plus `AnalyticsDefinitionValidator` for references and dependency cycles.
- **Metrics:**
  - aggregates: count, sum, avg, min, max and count_distinct;
  - composites: ratio, percentage and rate, and derived metrics using the safe `Formula` (no PHP evaluation);
  - explicit null semantics, division-by-zero protection and precision;
  - `MetricCalculator`, `MetricHandle` and `MetricResult`.
- **Queries:**
  - `AnalyticsQuery` and `AnalyticsQueryPlanner`, which whitelist fields, aggregates, dimensions, operators and values, and enforce period, point, group, row and page limits;
  - `EloquentAnalyticsExecutor`: SQL aggregation on gcego/data's scoped builder, the dataset `AnalyticsScope`, bound values, an optional statement timeout, small-group suppression, and `aggregateOn()` for trusted providers;
  - the `AnalyticsProvider` contract.
- **Time:**
  - `DateRange` with presets and time-zone-exact boundaries;
  - `TimeInterval` (hour, day, ISO week, month, quarter, year) and driver-specific `BucketExpression`s;
  - gap filling (`FillStrategy`);
  - `Comparison` and `ComparisonResult` (previous period, previous year, custom).
- **Reports:** `ReportManager` provides:
  - filters (`FilterResolver`) and sortable columns;
  - server-side pagination with totals;
  - labels through gcego/data;
  - authorized drill-down;
  - columns hidden from viewers who may not use their fields.
- **Dashboards:**
  - `DashboardResolver`, with each part authorized and failing on its own;
  - `DashboardState`, built from validated input;
  - charts through gcego/widgets' Chart.js adapter (`ChartBuilder`), with summaries and data tables.
- **Security:**
  - `AnalyticsAuthorizer`: deny by default, requirements evaluated as the viewer in the tenant, field and dimension authorization, export and schedule permissions;
  - `AnalyticsContext` and `ContextScope`: tenant membership verified when switching context.
- **Exports:**
  - CSV, and XLSX through openspout, with formula-injection protection;
  - `ExportManager`: sync or queued (`GenerateAnalyticsReport`), chunked, size and concurrency limits, private unguessable storage;
  - signed, expiring, re-authorized downloads, pruning, notifications.
- **Scheduled reports:**
  - `ScheduledReport` and `ReportScheduleManager`: recipient eligibility, atomic claims, re-verification on every run, pausing on lost access;
  - `DeliverScheduledReport`, `gcego:analytics:run-schedules` and scheduler registration.
- **Caching:** `AnalyticsCache` keys combine the definition fingerprint, audience (user and tenant), request and dataset versions; flushing and per-viewer refresh are supported.
- **UI:**
  - Livewire `gcego-analytics-dashboard` and `gcego-analytics-report`, with Alpine disclosures;
  - panel pages `<panel>/analytics/{dashboard}`, `<panel>/reports` and `<panel>/reports/{report}`;
  - styling slots on `Dashboard::class`, using only classes already compiled by other packages;
  - English and French translations.
- **Integrations:**
  - gcego/widgets: `MetricValue` and `MetricTrend`;
  - gcego/tables: `ReportRows`;
  - gcego/actions: `ExportReport` and `RefreshAnalytics`;
  - gcego/workflows datasets and metrics (`WorkflowAnalytics`);
  - gcego/notifications category `reports`.
- **Diagnostics:** `AnalyticsInspector` and `gcego:analytics:inspect`.
- **gcego/data addition:** `DataManager::scopedQuery()` and `Contracts\ProvidesScopedQueries`.
