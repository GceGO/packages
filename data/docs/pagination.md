# Pagination

Sources are always paginated; there is no "load everything" mode.

```php
Pagination::make(25);            // page numbers with a total (COUNT + SELECT)
Pagination::simple(50);          // page numbers, no COUNT: "has more" only
Pagination::cursor(50);          // keyset pagination: no offsets, no COUNT
Pagination::make(25, max: 50);   // requests may ask for up to 50 per page
```

| Parameter | Rule |
| --- | --- |
| `page` | positive integer, at most the deepest offset (`performance.max_offset`, 100,000 rows, or `Limits::make(maxOffset: ...)`). Pages past the end return no items. |
| `per_page` | positive integer, capped at the source's `max`, which never exceeds `pagination.max_per_page` (100). |
| `cursor` | cursor sources only. A cursor that does not match the ordering returns 400. |

Without `Pagination`, `pagination.default_per_page` (25) applies.

## Modes

| Mode | Queries (Eloquent) | Metadata |
| --- | --- | --- |
| Pages | count + select | total, lastPage, from, to |
| Simple | select (one extra row) | hasMorePages |
| Cursor | select | nextCursor, previousCursor |

- **Cursor ordering:** the key is always appended; sorting by a relation
  aggregate alias is rejected for cursor sources.
- **Provider support:** collection sources do not support cursors.
- **Caching:** cursor pages are never cached.
- **Transforms:** cursors are computed before `Transform` runs.

```php
$next = Data::query($source, ['cursor' => $page->pagination->nextCursor]);
```

## Measured

`benchmarks/run.php`, PHP 8.5, in-memory SQLite, 10,000 rows (numbers vary
by machine):

| Case | Per call | Queries |
| --- | --- | --- |
| Page 1, page-number mode | 1.21 ms | 2 |
| Page 1, simple | 0.69 ms | 1 |
| Page 1, cursor | 0.74 ms | 1 |
| Page about 390 of 400 | 1.82 ms | 2 |
