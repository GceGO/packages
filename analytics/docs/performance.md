# Performance

## Query cost

- **Aggregation** happens in SQL, so rows are never loaded into PHP.
- **Metric values** cost one query; a comparison adds one, and a composite costs one per metric it uses.
- **Series and groups** cost one query each.
- **Report pages** cost three queries: rows, the count of groups and the labels.
- **Tenant membership** is verified once per context switch (`ContextScope`) and memoized per request.
- **Indexes:** index the dataset's date column together with its tenant column, plus frequently grouped dimensions. For example, on the fixture: `(institution_id, submitted_at)` and `(institution_id, subject_id)`.

## Cache

- **Lifetime:** `cache.ttl`, or a metric's `cache:` seconds.
- **Isolation:** keys isolate definition, audience, request and dataset versions.
- **Invalidation:** `flush($dataset)` bumps a dataset's version for everyone, for example after imports; the `RefreshAnalytics` action does the same. "Refresh" on a dashboard affects only the current viewer.
- **Payloads:** Livewire components store only state (names, period, filters), never results; snapshots stay well under 1.5 KB.

## Exports

Exports read in `exports.chunk` pages, write to a temporary file and stream it to the disk, so memory stays flat for large exports. Large exports are queued.

## Precomputed aggregates

Not included. Caching keeps repeated dashboards cheap: a second identical request issues no data query. Database aggregation handled the tested volume (three metrics over 20,000 rows within the test's 3-second bound on in-memory SQLite).

A materialized aggregate store would need refresh, consistency, tenant isolation and version rules of its own. Add it when measured load requires it, for example as a trusted `AnalyticsProvider` over a summary table.

## Measured

Tests use SQLite in memory.

| Measurement | Result |
| --- | --- |
| Three metrics (with comparison, series and composite) over 20,000 rows | 6 data queries, under 3 s including seeding overhead |
| Report page 50 of 300 groups | 3 data queries |
| Export of 303 groups in chunks of 50 | Memory growth under 64 MB |
| 800-group grouping | Capped at 500 and reported as truncated |
