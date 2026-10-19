# Charts

```php
new Chart(new Property(Chart::Name, 'enrolment'), new Property(Chart::Type, 'line'), new DataProvider(StudentEnrolmentProvider::class))
new BarChart('Students by level', new Aggregate(Student::class, groupBy: 'level'), new Property(BarChart::Horizontal, true))
new AreaChart('Payments', new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at', bucket: 'month'), new DateRange(default: 'this_year'))
new PieChart('Level share', new Aggregate(Student::class, groupBy: 'level'))
new DoughnutChart('Revenue by method', new Aggregate(Payment::class, 'sum', 'amount', groupBy: 'method'), new Property(Chart::Format, 'currency'))
```

**Properties:**
- `Type` (line, bar, area, pie, doughnut);
- `Horizontal` (bars) and `Stacked`;
- `Legend` (default: on for several datasets and for pie or doughnut);
- `Summary`, which replaces the generated summary;
- `Height` (sm, md, lg);
- `Format`, `Decimals` and `Currency` for tooltips, axes, summary and table.

## Data

Charts take their data from:
- `WidgetDataResult::series($label, [label => value])`;
- `WidgetDataResult::chart($labels, [new ChartDataset($label, $values, $tone)])`;
- an `Aggregate` grouped by a column or a time bucket. Buckets are
  zero-filled across the period and labelled by locale ("Mar 2026").

## Normalization (ChartDataNormalizer)

| Rule | Details |
| --- | --- |
| Limits | At most `charts.max_points` (500) labels and `charts.max_datasets` (10) datasets. |
| Shape | Every dataset has one value per label. |
| Values | Finite numbers or null; anything else is refused. |
| Pie and doughnut | One dataset of non-negative values. |
| Tones | From a fixed list (primary, success, warning, danger, info, neutral). |

Invalid data shows the widget's error state and is reported. A partial
chart is never drawn.

## The browser adapter

| Topic | Details |
| --- | --- |
| What the server sends | `ChartJsAdapter` emits a small description as JSON in `<script type="application/json">`, escaped with `JSON_HEX_*`. |
| Building the chart | `resources/js/charts/config.js` re-validates that description and builds the Chart.js configuration. Every function (tooltips, ticks) is defined there; no options or code come from the server or from data. |
| Loading | `index.js` registers the Alpine component `gcegoChart`, which imports the adapter lazily. |
| Lifecycle | Each chart is destroyed when its element is removed, and Livewire re-keys the element when the data changes, so there are no duplicate instances or leaks. |
| Theming | Colors come from the gcego CSS tokens, so dark mode applies, and reduced motion disables animation. |
| Size | Measured with `npm run build:measure`: the entry is 2.16 kB (1.07 kB gzipped); the Chart.js chunk is 191.5 kB (66.8 kB gzipped), loaded only when a chart is on the page. |

Other libraries plug in through `Contracts\ChartAdapter` and
`ChartAdapters::register()`, plus a browser module.

## Accessibility

Every chart is a `<figure>` with:
- a heading;
- a `role="img"` canvas labelled with a generated summary of each series
  (points, total, highest and lowest);
- a visible `<figcaption>`;
- the full data as a `<table>` inside `<details>`.

Nothing depends on seeing the chart.
