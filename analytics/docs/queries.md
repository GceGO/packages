# Queries

```php
$rows = $analytics->query(new AnalyticsQuery(
    dataset: app(AnalyticsDefinitions::class)->dataset('assessment-results'),
    measures: [new Measure('attempts', Aggregate::Count), new Measure('average', Aggregate::Average, 'score')],
    dimensions: ['level'],
    filters: [new AnalyticsFilter('kind', FilterOperator::In, ['objective', 'structured'])],
    range: DateRange::preset('last_30_days', 'Africa/Douala'),
    sort: ['average' => 'desc'],
    limit: 20,
));
```

## Planner rules

`AnalyticsQueryPlanner` enforces these before anything runs:

1. The viewer may use the dataset.
2. Measures use declared, authorized fields with allowed aggregates.
3. There are at most three dimensions, each declared and authorized.
4. Filters target filterable fields and dimensions, with operators that fit:
   - `eq`, `neq`, `in`, `not_in` and `null` / `not_null` everywhere;
   - comparisons and `between` on fields only.
5. Filter values are type-checked: numbers, whole numbers, booleans, dates (`YYYY-MM-DD[ HH:MM[:SS]]`) or strings of at most 255 characters. Option dimensions accept only their keys, and lists hold at most 100 values.
6. Periods and series need a date column.
7. Periods last at most `max_range_days`.
8. Series have at most `max_chart_points` buckets.
9. Sorting uses measure aliases or grouped dimensions only.
10. Rows are bounded:
    - groups by `max_group_cardinality`;
    - series by `max_chart_points`;
    - everything by `max_rows`, with offsets inside it;
    - reports page by `max_page_size`;
    - exports by `exports.max_rows`.

Rejections raise `AnalyticsQueryException`, which carries a stable `reason`, and dispatch `AnalyticsQueryRejected`.

## Execution

The executor builds SQL only from plan columns wrapped by the query grammar, fixed aggregate SQL and fixed bucket formats. Every value is bound.

| Driver | Time buckets |
| --- | --- |
| PostgreSQL | Converted exactly (`AT TIME ZONE`). |
| MySQL, MariaDB, SQLite | Shifted by the zone's offset at the start of the period: a daylight-saving change inside the period moves that hour's boundary. |
| SQL Server | Not supported for time series. |

`queries.timeout_ms` sets a statement timeout on MySQL and PostgreSQL.

## Periods

| Preset | Period |
| --- | --- |
| `today`, `yesterday` | That day |
| `last_7_days`, `last_30_days`, `last_90_days` | Including today |
| `current_month`, `previous_month` | A calendar month |
| `current_year`, `previous_year` | A calendar year |
| `DateRange::between('2026-01-01', '2026-01-31', $tz)` | A custom range |

Comparisons:

- **`previous_period`:** the same length immediately before; a whole-month period compares with the same number of whole months.
- **`previous_year`:** the same days one year earlier.
