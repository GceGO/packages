# API reference

Namespace `Gcego\Widgets`.

## Widgets

| Class | Notes |
| --- | --- |
| `Widget` (abstract) | `Name`, `Label`, `Description`, `Icon`, `Value`, `Span`, `Height`, `MinWidth`, `Empty`, `Interactive`; `widgetName()`, `title()`, `dataSource()`, `dateRange()`, `refresh()`, `cache()`, `requirements()`, `needsLivewire()`, `layout()`, `viewData()`, `view()` |
| `Statistics\Stat` | KPI formats (`Format`, `Decimals`, `Currency`, `Prefix`, `Suffix`, `PercentScale`), `Tone`, `Url`; `trend()`, `formatValue()`, `describeTrend()` |
| `Statistics\Stats` | `Columns`; children are `Stat`s |
| `Statistics\TrendResult` | `between()`, `direction`, `difference`, `percent`, `sentiment`, `tone()` |
| `Charts\Chart`, `LineChart`, `BarChart`, `AreaChart`, `PieChart`, `DoughnutChart` | `Type`, `Horizontal`, `Stacked`, `Legend`, `Summary`, KPI formats; `summarize()` |
| `Charts\ChartData`, `ChartDataset`, `ChartDataNormalizer`, `ChartJsAdapter`, `ChartAdapters` | chart data and adapters |
| `Progress\Progress` | `Maximum`, `Minimum`, `Tone`, KPI formats; `percent()` |
| `Progress\Goal` | `Target`, `Deadline`, `Direction` (at_least, at_most); `evaluate()` |
| `Activity\ActivityFeed` | `Limit`, `Pages` |
| `Activity\ActivitySource`, `ActivityItem` | feed source and items |
| `Records\RecentRecords` | `Blueprint`, `TitleColumn`, `TimeColumn`, `DescriptionColumn`, `BadgeColumn`, `Route`, `RouteParameters` |
| `Records\RecordSummary` | `Blueprint` (gcego/infolists) |
| `Records\TableWidget` | `Blueprint`, `MaxHeight` (gcego/tables) |

## Configuration nodes

| Node | Signature |
| --- | --- |
| `Data\Value` | `(Closure\|int\|float\|string\|null)` |
| `Data\DataProvider` | `(class-string<WidgetDataProvider>)` |
| `Data\Aggregate` | `(string $source, $function = 'count', ?column, ?groupBy, ?date, $bucket = null, bool $compare = false, int $limit = 100, list<Filter> $filters = [], ?label)` |
| `Data\DateRange` | `(string $default = 'last_30_days', list<string> $presets, bool $custom = true)`; `resolve()` returns a `Data\Period` (`from`, `to`, `previous()`, `days()`, `toArray()`) |
| `Configuration\Trend` | `(increase: positive\|negative\|neutral, show: percent\|absolute\|both, ?label)` |
| `Configuration\Refresh` | `(?int $every, list<string> $on)` |
| `Configuration\Cache` | `(?int $ttl, string $scope = 'user'\|'tenant')` |

Widgets also accept gcego/security requirements and `Gcego\Chroma\Tailwind`.

## Data

| Class | Notes |
| --- | --- |
| `Contracts\WidgetDataProvider` | `resolve(WidgetDataContext): WidgetDataResult` |
| `Data\WidgetDataContext` | `widget`, `path`, `user`, `tenant`, `period`, `record`, `page`, `locale`, `timezone`; `aggregate()`, `count()`, `query()`, `with()` |
| `Data\WidgetDataResult` | `value()`, `progress()`, `series()`, `chart()`, `items()`, `values()`, `empty()`, `for()` |
| `Data\WidgetDataResolver` | `resolve()`, `forget()`, `cacheKey()` |
| `State\WidgetState` | `from(Widget, array, WidgetsConfig)`; `period`, `page` |

## WidgetManager (singleton)

| Method | Returns |
| --- | --- |
| `identity(string)` | `string` |
| `resolve(string)` | `Widget` (404 when unknown) |
| `locate(string $owner, string $path)` | `[owner, Widget]`: an embedded widget of a registered layout or blueprint (404 when unknown) |
| `authorize(ComponentInterface, ?path)`, `allows(ComponentInterface, ?path)` | `void` (403) / `bool` (a widget, or the owner of an embedded one) |
| `state(Widget, array)` | `WidgetState` |
| `context(string $identity, Widget, ?WidgetState, $record, ?path)` | `WidgetDataContext` |
| `data(Widget, WidgetDataContext, ?WidgetDataResult $shared)` | `WidgetDataResult` |
| `render(string $identity, array $state = [], $record = null)` | `HtmlString` |
| `renderWidget(..., ?owner, ?path)` | `HtmlString` |
| `forget(string $identity)` | `void` (a widget, or an owner: every embedded widget of it) |
| `inspect(string\|Widget)` | `Diagnostics\WidgetReport` |

## Other classes

| Class | Notes |
| --- | --- |
| `Livewire\WidgetHost` | alias `gcego-widget` |
| `Rendering\WidgetRenderer`, `WidgetRenderContext`, `WidgetRenderFrame` | rendering |
| `Registry\WidgetKind` | the registry kind |
| `Support\KpiFormat` | KPI formatting |
| `Presets\WidgetPresets` | literal classes |
| `Contracts\ChartAdapter` | `id()`, `module()`, `config(Chart, ChartData)` |

Diagnostic checks:
- `schema`, `registry`, `authorization`, `source`;
- `refresh`, `cache`, `chart`, `renderer`, `styling`, `performance`.

## Configuration (`config/gcego-widgets.php`)

| Key | Default |
| --- | --- |
| `charts.default_adapter` | `chartjs` |
| `charts.max_points` | 500 |
| `charts.max_datasets` | 10 |
| `refresh.minimum_interval_seconds` | 30 |
| `cache.enabled` | false |
| `cache.store` | null |
| `cache.ttl` | 300 |
| `activity.default_limit` | 10 |
| `activity.max_limit` | 50 |
| `dates.timezone` | null |
| `dates.max_range_days` | 732 |
| `formatting.currency` | XAF |

The prompt's `charts.lazy_load` and `refresh.default` options were left
out: lazy loading is always on, and the default is always manual refresh.

## Exceptions (`Exceptions\WidgetException`)

| Exception | When |
| --- | --- |
| `InvalidWidgetException` | definitions |
| `InvalidWidgetStateException` | state; HTTP 400 |
| `WidgetDataException` | invalid provider data |

## Browser module (`resources/js/charts`)

| File | Exports |
| --- | --- |
| `index.js` | `gcegoChart()`, `registerGcegoCharts(Alpine)` |
| `config.js` | `validate()`, `formatter()`, `toChartJs()`, `palette()` |
| `chartjs.js` | `render(canvas, description)` |
