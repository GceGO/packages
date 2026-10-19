# Aggregation and date ranges

```php
new Aggregate(Student::class)                                                      // count
new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at', compare: true)     // in the period, with the previous period
new Aggregate(Student::class, 'avg', 'age', groupBy: 'level')
new Aggregate(Enrolment::class, date: 'created_at', bucket: 'month')               // a time series, zero-filled
new Aggregate('blueprint:students', filters: [new Filter('status', 'active')])     // a blueprint's Source, its requirements checked
```

## How it runs

| Rule | Details |
| --- | --- |
| Engine | `Aggregate` runs `DataManager::aggregate()` from gcego/data. |
| Authorization | The model's `viewAny` policy, or the blueprint Source's requirements. |
| Scopes | Mandatory constraints and tenant scopes apply. |
| Where | Aggregates (COUNT, SUM, AVG, MIN, MAX, GROUP BY a column or a date bucket) run in the database. Nothing is loaded into PHP. |
| Bounds | Groups are capped by `limit` (100 by default, at most 1000). |
| Columns | Plain fields of the source, validated and wrapped by the grammar. Every value is a binding. |
| Time buckets | Computed in the storage (application) zone: SQLite, MySQL/MariaDB, PostgreSQL and SQL Server are supported. Week numbering follows the database, and week buckets are not zero-filled. |

## Date ranges

```php
new DateRange()                                                                // last_30_days by default, all presets, custom
new DateRange(default: 'this_month', presets: ['this_month', 'last_month', 'this_year'], custom: false)
```

| Topic | Details |
| --- | --- |
| Presets | today, yesterday, last_7_days, last_30_days, this_month, last_month, this_year, custom. |
| Boundaries | Day boundaries in the display zone (`dates.timezone`, else `app.timezone`), converted to the storage zone. Inclusive. |
| Comparison | `Period::previous()` is the same length immediately before. |
| Custom ranges | At most `dates.max_range_days`. Invalid or forged ranges are refused (400), or reset with a notice in Livewire. |
| Scope | The range applies to every Aggregate with a `date` column in the widget, including the stats of a group. Providers read `$context->period`. |
