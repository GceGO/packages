# Reports

A report groups by its dimension columns and measures with its aggregate columns:

- **Columns:** `new ReportColumn('subject_id', 'Subject')` groups by a dimension; `new ReportColumn('avg', 'Average', aggregate: 'avg', field: 'score', precision: 1)` measures.
- **Formats:** text, number, percentage, currency, date, datetime, status, duration.
- **Filters:**
  - `new ReportFilter('level', 'Level')` takes one value;
  - `multiple: true` takes several (`in`);
  - `operator: 'gte' | 'lte'` compares fields;
  - `default:` and `required:` are also available.
- **Report options:** `dataset`, `dateColumn`, `defaultRange`, `perPage` (≤ `max_page_size`), `sort` / `direction`, `export` / `formats` / `exportAbility`, `schedulable`.

## Running

```php
$result = $analytics->report('student-performance')->run(filters: ['level' => 'o'], range: 'last_30_days', sort: 'attempts', direction: 'asc', page: 2);
$result->rows;         // [['cells' => ['subject_id' => ['value' => 'Mathematics', 'formatted' => 'Mathematics', 'key' => '12'], ...], 'suppressed' => false], ...]
$result->total;        // number of groups
$result->lastPage();
```

A page costs one grouped query, one count of groups and one label query per labelled dimension. Ordering is deterministic, falling back to every group key.

## Drill-down

`drillDown: 'other-report'` on a dimension column links each row to that report, filtered by the row's key. The link appears only if both of these hold:

1. the viewer may open the target report;
2. the target declares a `ReportFilter` on that dimension.

Opening the link runs the target with its own authorization and validation.

## Livewire

`<livewire:gcego-analytics-report report="…" />` keeps its state in the URL: filters, period, sort, direction and page. Every request validates that state. The component sorts, paginates and exports, and shows the generation time and whether the result came from the cache.
