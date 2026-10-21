# gcego/widgets

Declarative dashboard widgets for GCEGO Admin Studio:
- KPI cards with trends and statistic groups;
- charts (line, bar, area, pie, doughnut) drawn with Chart.js;
- progress bars, goals and activity feeds;
- recent records, record summaries (gcego/infolists) and table widgets (gcego/tables).

Data comes from secure database aggregates (gcego/data) or trusted
providers. Widgets support date ranges, refresh and opt-in caching, and
render as accessible Tailwind markup, statically or through Livewire.

```php
// app/GCEGo/Widgets/TotalStudents.php — discovered as "widget:total_students"
return new Stat('Total Students', new Aggregate(Student::class), new Ability('viewAny', Student::class));
```

```blade
<x-gcego-widgets::widget name="total_students" />          {{-- static Blade, or Livewire when the widget needs it --}}
<livewire:gcego-widget widget="enrolment" />                 {{-- interactive: date range, refresh --}}
```

## Examples

```php
new Stat('Total Students', StudentCountProvider::class)                                    // a WidgetDataProvider class
new Stat('Total Students', fn (WidgetDataContext $context) => $context->count(Student::class))  // evaluated at render time only

new Stat(new Property(Stat::Name, 'total_students'), new Property(Stat::Label, 'Total Students'), new Value(fn () => …))

new Stats(
    new Stat('Students', StudentCountProvider::class),
    new Stat('Teachers', TeacherCountProvider::class),
)

new Stat('Revenue', new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at', compare: true), new Trend(),
    new Property(Stat::Format, 'currency'), new DateRange(default: 'this_month'))
new Stat('Failed assessments', new Aggregate(Result::class, filters: [new Filter('passed', false)], date: 'graded_at', compare: true),
    new Trend(increase: 'negative'))                      // an increase is bad

new Chart(new Property(Chart::Name, 'enrolment'), new Property(Chart::Type, 'line'), new DataProvider(StudentEnrolmentProvider::class))
new BarChart('Students by level', new Aggregate(Student::class, groupBy: 'level'))
new LineChart('Enrolment', new Aggregate(Student::class, date: 'enrolled_at', bucket: 'month'), new DateRange(default: 'this_year'))

new Progress(new Property(Progress::Name, 'completion'), new Property(Progress::Value, 72), new Property(Progress::Maximum, 100))
new Goal('Enrolment target', new Aggregate(Student::class), new Property(Goal::Target, 1500), new Property(Goal::Deadline, '2026-09-30'))

new ActivityFeed('Recent activity', new ActivitySource(ActivityLog::class, title: 'description', time: 'created_at', actor: 'causer.name'))
new RecentRecords('New students', new Property(RecentRecords::Blueprint, 'students'), new Property(RecentRecords::TimeColumn, 'enrolled_at'))
new RecordSummary('Student', new Property(RecordSummary::Blueprint, 'students'))           // record from the host
new TableWidget('Overdue invoices', new Property(TableWidget::Blueprint, 'overdue_invoices'))

new Stat(new Property(Stat::Name, 'total_students'), new Property(Stat::Label, 'Total Students'), new Value(fn () => …),
    new Tailwind(new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'p-6', 'shadow-sm'])))
```

`new Property(Stat::Value, fn () => …)` is not possible: gcego/components
refuses closures in properties by design. Use a positional closure, `new
Value(fn () => …)`, or `gcego/data`'s `Callback`.

## Installation

The package is a path repository in this monorepo (`gcego/widgets: ^0.1`).
Charts need Chart.js in the host application and the adapter module in
its Vite entry:

```bash
npm install chart.js@^4.5.1
```

```js
// resources/js/app.js
import '../../packages/widgets/resources/js/charts/index.js';
```

Notes:
- The entry is about 1 kB gzipped. Chart.js (about 67 kB gzipped) is imported
  lazily the first time a chart appears.
- Without the module, charts still show their text summary and data table.
- Add `widgets` to the CSS `@source` list (gcego/chroma's `gcego.css`
  already includes it).

## Documentation

| Topic | |
| --- | --- |
| [Architecture](docs/architecture.md) | definitions, registry, data, rendering |
| [Statistics](docs/statistics.md) | Stat, Stats, KPI formats, trends |
| [Charts](docs/charts.md) | types, data, normalization, the Chart.js adapter, accessibility |
| [Data providers](docs/data-providers.md) | Value, DataProvider, WidgetDataContext, WidgetDataResult |
| [Aggregation](docs/aggregation.md) | Aggregate and gcego/data aggregation, date ranges |
| [Refresh and caching](docs/refresh.md) | refresh policies, polling, events, caching |
| [Livewire](docs/livewire.md) | WidgetHost, state, records |
| [Styling](docs/styling.md) | slots, presets, layout metadata |
| [Security](docs/security.md) | authorization, tenancy, cache isolation, safe serialization |
| [API reference](docs/api-reference.md) | everything |

## Testing

```bash
composer test     # Pest: unit, integration, Livewire, security, performance
npm test          # node:test for the chart adapter
npm run build:measure   # bundle sizes (uses the root application's Vite)
composer analyse  # PHPStan (max)
```
