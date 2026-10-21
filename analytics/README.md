# gcego/analytics

Declarative analytics for GCEGO Admin Studio: datasets, metrics, reports and dashboards declared in a few lines of PHP. Each one is validated, authorized and tenant-scoped, and aggregated in the database.

Definitions are gcego/components discovered by gcego/registry, so authors never write analytics controllers, chart endpoints, export downloads or scheduling jobs. Analytics owns the calculations and reuses the rest of GCEGO:

| Need | Provided by |
| --- | --- |
| Rows a viewer may see | gcego/data (policies, global scopes, tenancy) |
| Access checks | gcego/security |
| Charts (Chart.js) and KPI cards | gcego/widgets |
| Pages | gcego/panels |
| Deliveries | gcego/notifications |
| Jobs and schedules | Laravel's queue and scheduler |

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Definitions and discovery](#definitions-and-discovery)
- [Datasets](#datasets)
- [Metrics](#metrics)
- [Querying](#querying)
- [Reports](#reports)
- [Dashboards and charts](#dashboards-and-charts)
- [Exports and scheduled reports](#exports-and-scheduled-reports)
- [Security, tenancy and privacy](#security-tenancy-and-privacy)
- [Caching and performance](#caching-and-performance)
- [Integrations](#integrations)
- [Configuration](#configuration)
- [Accessibility](#accessibility)
- [Testing and troubleshooting](#testing-and-troubleshooting)

The guides in `docs/` cover each part in depth:

| Guide | Covers |
| --- | --- |
| [architecture](docs/architecture.md) | How the parts fit together |
| [datasets](docs/datasets.md) | Datasets and dimensions |
| [metrics](docs/metrics.md) | Metrics and formulas |
| [queries](docs/queries.md) | The query engine and its limits |
| [reports](docs/reports.md) | Reports, filters and drill-down |
| [dashboards](docs/dashboards.md) | Dashboards and charts |
| [exports](docs/exports.md) | Exports and scheduled reports |
| [security](docs/security.md) | Authorization, tenancy and privacy |
| [performance](docs/performance.md) | Caching, limits and query cost |
| [api-reference](docs/api-reference.md) | Classes, methods and events |

## Installation

```bash
composer require gcego/analytics
php artisan migrate        # gcego_analytics_exports, gcego_analytics_schedules
php artisan vendor:publish --tag=gcego-analytics-config   # optional
```

| Requirement | Notes |
| --- | --- |
| PHP 8.3+, Laravel 13, Livewire 4 | |
| GCEGO packages listed in `composer.json` | |
| `openspout/openspout` | Optional; enables XLSX exports. |
| Charts script | Import gcego/widgets' chart script into your Vite build: `import '…/packages/widgets/resources/js/charts/index.js'`. Analytics ships no JavaScript of its own. |
| Queue worker | Run one for the `analytics` queue; large exports and scheduled reports are queued. |
| Laravel's scheduler | Runs `gcego:analytics:run-schedules` every five minutes and `gcego:analytics:prune-exports` hourly. |

## Quick start

Create `app/GCEGo/Analytics/Datasets/assessment-results.php`:

<!-- example:dataset -->
```php
use App\Models\AssessmentResult;
use App\Models\Subject;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Security\Requirements\Ability;

return new Dataset('assessment-results', AssessmentResult::class,
    new Ability('analytics.view'),
    new Field('score', 'Score', type: 'number'),
    new Field('user_id', 'Student', type: 'integer', aggregates: ['count_distinct'], filterable: false),
    new Field('submitted_at', 'Submitted', type: 'datetime'),
    new Dimension('subject_id', 'Subject', labelModel: Subject::class),
    new Dimension('level', 'Level', options: ['o' => 'Ordinary Level', 'a' => 'Advanced Level']),
    new Dimension('status', 'Status', options: ['started' => 'Started', 'completed' => 'Completed']),
    dateColumn: 'submitted_at',
    minimumGroupSize: 5,
);
```

Then add metrics in `app/GCEGo/Analytics/Metrics/` (one definition per file):

<!-- example:metric -->
```php
use Gcego\Analytics\Metrics\Metric;

return new Metric(
    name: 'average_score',
    label: 'Average score',
    dataset: 'assessment-results',
    aggregate: 'avg',
    field: 'score',
    precision: 1,
);
```

Calculate them anywhere, as the signed-in user in their tenant:

<!-- example:calculate -->
```php
use Gcego\Analytics\AnalyticsManager;

$analytics = app(AnalyticsManager::class);

$result = $analytics->metric('average_score')->calculate(range: 'last_30_days', comparison: 'previous_period');

return [$result->value, $result->formatted, $result->comparison?->direction];
```

## Definitions and discovery

Every file under `app/GCEGo/Analytics/{Datasets,Metrics,Reports,Dashboards}` (recursively) returns one definition. Each is registered in gcego/registry under its kind:

- `dataset:assessment-results`
- `metric:average_score`
- `report:student-performance`
- `analytics-dashboard:academic-overview`

Names are lowercase and stable; duplicates are refused. Each definition is validated when it is registered. References between definitions are checked when they are used, and listed by `php artisan gcego:analytics:inspect`. Constructing a definition never queries the database.

Every definition accepts three equivalent styles. Positional children and requirements come before named arguments, as PHP requires.

<!-- example:styles -->
```php
use App\Models\Student;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Components\Properties\Property;

return [
    new Metric('total_students', Student::class),                                                       // shorthand: name, model (a count)
    new Metric(name: 'total_students', label: 'Total Students', model: Student::class, aggregate: 'count'),
    new Metric(new Property(Metric::Name, 'total_students'), new Property(Metric::Model, Student::class)),
];
```

## Datasets

A dataset says what may be measured, grouped and filtered, and by whom:

- **Fields** are measurable or filterable columns. Their `type` decides the aggregates they allow, which can be narrowed further with `aggregates`. Fields marked `sensitive: true` need their own requirements and are never offered as dimensions.
- **Dimensions** group and filter. Their labels can come from `options`, from a `labelModel` read through gcego/data as the viewer, or from a trusted `labels:` resolver. Requirements on a dimension restrict it (for example, cross-institution grouping).

Only declared columns exist for analytics. Undeclared columns are never queryable, whatever the database holds.

| Option | What it does |
| --- | --- |
| `scope:` | A trusted `AnalyticsScope` that narrows rows per viewer before aggregation, for example so students see only their own attempts. |
| `minimumGroupSize:` | Suppresses groups smaller than that size (small-cell privacy). |
| `provider:` | A trusted `AnalyticsProvider` for data that isn't an Eloquent model. |

See [docs/datasets.md](docs/datasets.md).

## Metrics

| Aggregate | Meaning |
| --- | --- |
| `count`, `count_distinct` | database aggregates; empty sets are 0 |
| `sum` | database aggregate; empty sets are 0 |
| `avg`, `min`, `max` | database aggregates; empty sets have **no value** (`null`, shown "—") |
| `ratio` / `percentage` / `rate` | `numerator / denominator` (× 100, × `per`), from other metrics in the same context; a zero denominator gives no value |
| `derived` | a safe formula over other metrics, for example `{passed} / {sat} * 100` (numbers, `{metric}`, `+ - * /`, parentheses; never PHP) |

<!-- example:time-series -->
```php
use App\Models\Student;
use Gcego\Analytics\Metrics\Metric;

return new Metric(
    name: 'daily_registrations',
    label: 'Daily Registrations',
    model: Student::class,
    aggregate: 'count',
    dateColumn: 'created_at',
    interval: 'day',
);
```

Other options:

- `filters:` adds trusted conditions, for example `['status' => 'completed']`.
- `format`, `unit`, `precision` and `currency` control display.
- `cache:` sets the cache lifetime in seconds.
- `fill:` sets gap filling: `zero` for counts and sums, `null` for averages, or `none`.

A metric on a `model:` (an implicit dataset) needs its own requirements. Dependencies are cycle-checked. See [docs/metrics.md](docs/metrics.md).

## Querying

<!-- example:query -->
```php
use Gcego\Analytics\Filters\DateRange;

$daily = $analytics->metric('average_score')->calculate(range: 'last_7_days', interval: 'day');
$bySubject = $analytics->metric('average_score')->calculate(range: DateRange::between('2026-03-01', '2026-03-15', 'Africa/Douala'), groupBy: 'subject_id', limit: 5);
$ordinaryLevel = $analytics->metric('average_score')->calculate(range: 'last_30_days', filters: ['level' => 'o']);

return [count($daily->series), $bySubject->groups[0]['label'], $ordinaryLevel->value];
```

**Periods.** Ranges are inclusive calendar days in the viewer's time zone, bound to SQL as `[start, end)`. The presets are:

| Days | Months and years |
| --- | --- |
| `today`, `yesterday` | `current_month`, `previous_month` |
| `last_7_days`, `last_30_days`, `last_90_days` | `current_year`, `previous_year` |

For any other range, use `DateRange::between()`.

**Intervals.** `hour`, `day`, `week` (ISO, keyed by Monday), `month`, `quarter` and `year`.

**Comparisons.** `previous_period` (the same length immediately before; whole months compare to whole months) or `previous_year`. Each comparison returns the change, the percentage change (none when the base is 0) and the direction.

`$analytics->query(new AnalyticsQuery(...))` runs custom queries through the same planner. Every field, dimension, operator, value, period and size is validated against the viewer's schema first. See [docs/queries.md](docs/queries.md).

## Reports

<!-- example:report -->
```php
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportColumn;
use Gcego\Analytics\Reports\ReportFilter;
use Gcego\Security\Requirements\Ability;

return new Report('student-performance', 'Student Performance Report',
    new Ability('reports.academic'),
    new ReportColumn('subject_id', 'Subject'),
    new ReportColumn('attempts', 'Attempts', aggregate: 'count'),
    new ReportColumn('students', 'Students', aggregate: 'count_distinct', field: 'user_id'),
    new ReportColumn('average_score', 'Average score', aggregate: 'avg', field: 'score', precision: 1),
    new ReportFilter('level', 'Level'),
    dataset: 'assessment-results',
    description: 'Academic performance across subjects and assessments.',
    defaultRange: 'last_30_days',
    sort: 'average_score',
    export: true,
);
```

Run a report like this:

```php
$analytics->report('student-performance')->run(filters: ['level' => 'o'], page: 2, sort: 'attempts', direction: 'asc');
```

Rows are grouped by the dimension columns and computed in the database. Pagination runs on the server, with stable ordering and a total, and results are bounded.

**Drill-down.** `drillDown: 'other-report'` on a dimension column links each row to that report, filtered by the row's key. The link is shown only when the target report allows the viewer and declares that filter, and it is authorized again when opened. See [docs/reports.md](docs/reports.md).

## Dashboards and charts

<!-- example:dashboard -->
```php
use App\Models\Student;
use App\Models\Subject;
use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Dashboards\Chart;
use Gcego\Analytics\Dashboards\Kpi;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Reports\ReportFilter;
use Gcego\Security\Requirements\Ability;

return new Dashboard('academic-overview',
    new Ability('analytics.view'),
    new Metric(name: 'total_students', label: 'Total Students', model: Student::class, aggregate: 'count'),
    new Metric(name: 'total_subjects', label: 'Total Subjects', model: Subject::class, aggregate: 'count'),
    new Kpi('average_score'),
    new Chart('scores', 'Average score by subject', metric: 'average_score', groupBy: 'subject_id', type: 'bar'),
    new ReportFilter('level', 'Level'),
    title: 'Academic Overview',
);
```

| Part | How it works |
| --- | --- |
| Inline metrics | Belong to the dashboard and inherit its requirements. |
| `Kpi` | Names a registered metric. |
| `Chart` | Shows a metric over an interval (with an optional comparison overlay) or by a dimension (top N plus "Other"). Rendered by gcego/widgets' Chart.js adapter, with a summary and the data as a table. |
| `ReportSection` | Embeds a report's first page. |
| `ReportFilter` | Applies to every part whose dataset has that dimension. |

Each part is authorized on its own; parts the viewer may not see are omitted. See [docs/dashboards.md](docs/dashboards.md).

## Exports and scheduled reports

Reports with `export: true` can be exported to CSV, or to XLSX when openspout is installed. Viewers need report access plus `exportAbility` when it is set. Exports are:

- limited in size (`exports.max_rows`) and in how many each user may run at once;
- generated in the request when small, otherwise queued after the commit and read in chunks;
- stored privately under unguessable paths;
- downloadable through signed, expiring links that are re-authorized when followed. The downloader must be the owner or a recipient, in the same tenant, and still able to see the report.

Text cells that spreadsheets would run as formulas are neutralized.

Reports with `schedulable: true` can be scheduled with `ReportScheduleManager::create()`: daily, weekly or monthly at a local time, with a relative period, filters and recipients. Every run checks the owner and each recipient again, generates the export and notifies through gcego/notifications. Schedules whose owner lost access are paused. See [docs/exports.md](docs/exports.md).

## Security, tenancy and privacy

Authorization applies before aggregation and is denied by default:

- **Requirements:** datasets, reports and dashboards need gcego/security requirements; a definition with none is denied. They are evaluated as the viewer, in the viewer's tenant.
- **Rows:** come only from gcego/data's scoped builder (policy `viewAny`, global scopes, soft deletes, tenant scope), then the dataset's record scope.
- **Fields and dimensions:** sensitive fields and restricted dimensions are left out of the viewer's schema and refused in queries.
- **Tenants:** a forged tenant fails the membership check. Caches, exports and schedules are scoped per user and tenant.
- **Untrusted input:** never becomes SQL. Columns come from definitions; values are bound.
- **Small groups:** groups smaller than the minimum size are suppressed.

See [docs/security.md](docs/security.md).

## Caching and performance

Results are cached, for `cache.ttl` by default. Cache keys include:

- the definition fingerprint;
- the user and tenant;
- the request (period, comparison, filters, grouping, page);
- the version of each dataset involved.

To invalidate, `$analytics->flush('dataset')` bumps that dataset's version for everyone, and a dashboard's "Refresh" bypasses the cache for the current viewer only.

Limits are enforced on the server: rows, chart points, groups, period length, page size, export size and an optional statement timeout. Aggregates never load rows into PHP. Report pages cost three queries: the rows, the count of groups and the labels. Precomputed aggregates are not included; see [docs/performance.md](docs/performance.md).

## Integrations

| Package | Integration |
| --- | --- |
| gcego/data | `DataManager::scopedQuery()` (added for analytics) supplies every authorized base query. |
| gcego/widgets | `Integration\Widgets\MetricValue` (KPI with trend) and `MetricTrend` (series) providers; charts render through its Chart.js adapter. |
| gcego/tables | `Integration\ReportRows::source('report')`: report rows as a collection source for a Table. |
| gcego/actions | `Integration\Actions\ExportReport` (validated input becomes filters) and `RefreshAnalytics` handlers. |
| gcego/panels | `<panel>/analytics/{dashboard}`, `<panel>/analytics/reports` and `<panel>/analytics/reports/{report}`. |
| gcego/notifications | "report ready", "failed" and "scheduled" messages with signed download links (category `reports`). |
| gcego/workflows | `Integration\Workflows\WorkflowAnalytics` datasets and metrics; see below. |
| gcego/settings, gcego/studio | Not installed yet; not integrated. |

The workflow analytics register automatically when gcego/workflows is installed. They are:

- the datasets `workflow-instances` and `workflow-tasks`;
- the metrics `workflows_started`, `workflows_completed`, `pending_approvals`, `escalated_tasks`, `workflow_tasks` and `escalation_rate`.

They are tenant-scoped and protected by the workflow managers' ability.

## Configuration

`config/gcego-analytics.php` covers:

| Section | Settings |
| --- | --- |
| Discovery | paths |
| Time zone | default |
| Queries | `max_rows`, `max_chart_points`, `max_group_cardinality`, `max_range_days`, `max_page_size`, `timeout_ms` |
| Privacy | default minimum group size |
| Cache | enabled, TTL, store |
| Exports | queue, disk, directory, `max_rows`, `sync_rows`, chunk, TTL, `max_concurrent` |
| Scheduled reports | enabled, schedule, batch |
| Security | `require_authorization`, `enforce_tenant_scope` |
| Other | observability, integrations, panels |

Values are validated at boot. Limits can be lowered but not removed, and no setting turns authorization off.

## Accessibility

The views target WCAG 2.2 AA:

- **Controls:** every filter and period control is labelled; filter and export menus are keyboard-operable disclosures (Alpine) that close on Escape.
- **KPIs:** trends are spelled out ("up 50.0 % from 8"), never conveyed by colour or arrow alone.
- **Charts:** each has a summary sentence and a data table.
- **Report tables:** have captions, `aria-sort` and sort buttons with screen-reader text.
- **Announcements:** loading, results and errors use `role="status"` and `role="alert"`.
- **Styling:** focus styles and reduced-motion support come from gcego/chroma. No new CSS framework is introduced: the views reuse classes already compiled by widgets and tables.

## Testing and troubleshooting

```bash
composer test       # Pest: Unit, Integration, Feature, Security
composer analyse    # PHPStan (max)
composer format     # Pint
php artisan gcego:analytics:inspect   # definitions, authorization, tenant scopes, queue/cache/scheduler state
```

| Symptom | Cause |
| --- | --- |
| "You may not see this metric" | The viewer fails a requirement, the definition declares none, or the model has no `viewAny` policy (gcego/data denies). |
| "… is not an available field" | The field isn't declared, is sensitive, or doesn't allow that aggregate. |
| A period or interval is refused | `queries.max_range_days` or `max_chart_points`. |
| Charts don't draw | gcego/widgets' chart script isn't in your Vite build. The data table still shows. |
| Exports never finish | No worker on the `analytics` queue. |
