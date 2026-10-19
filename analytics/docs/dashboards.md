# Dashboards and charts

```php
new Dashboard('academic-overview',
    new Ability('analytics.view'),
    new Metric(name: 'subjects_offered', model: Subject::class),               // inline: inherits the dashboard's requirements
    new Kpi('attempts'),                                                       // a registered metric
    new Chart('attempts-over-time', 'Attempts', metric: 'daily_attempts', interval: 'day', compare: true),
    new Chart('score-by-subject', 'Average score by subject', metric: 'average_score', groupBy: 'subject_id', type: 'bar', limit: 10),
    new ReportSection('subject-performance'),
    new ReportFilter('level', 'Level'),
    title: 'Academic Overview', defaultRange: 'last_30_days', comparison: 'previous_period',
);
```

- **State:** `DashboardState::fromInput()` validates the period, the comparison and the shared filters. Filters must be declared, and they apply only where a part's dataset has that dimension.
- **Authorization:** each part is authorized separately. Hidden parts are omitted, and a failing part shows an error without breaking the others.
- **Charts:** `ChartBuilder` turns results into gcego/widgets `Chart` configurations, using the Chart.js adapter and the Alpine `gcegoChart` script. Each chart is re-keyed when its data changes (no duplicate instances or listeners) and comes with a summary sentence and a data table. Time series can overlay the comparison period; groupings show the top N, with "Other" for counts and sums.
- **Types:** line, bar, area, pie, doughnut.
- **Refresh:** "Refresh" bypasses the cache for the current viewer only, and `refresh:` documents the intended interval. No polling happens unless you add it.
- **Pages:** `<panel>/analytics/{dashboard}` renders `<livewire:gcego-analytics-dashboard>` inside any gcego/panels panel.
