# Pagination

Sources are always paginated; there is no "load everything" mode.

```php
new Paginate(25);               // page numbers with a total (COUNT + SELECT)
Paginate::simple(50);           // page numbers, no COUNT: "has more" only
Paginate::cursor(50);           // keyset pagination: no offsets, no COUNT
new Paginate(25, max: 50);      // requests may ask for up to 50 per page
```

| Parameter | Rule |
| --- | --- |
| `page` | positive integer. Pages past the end return no items (the total is still reported). |
| `per_page` | positive integer, capped at the source's `max`, which defaults to `pagination.max_per_page` (100) and never exceeds it. |
| `cursor` | for cursor sources only. A cursor that doesn't match the ordering returns 400. |

Without a `Paginate` node, `pagination.default_per_page` (25) applies.

## Modes

| Mode | Queries (Eloquent) | Metadata |
| --- | --- | --- |
| Pages | count + select | total, lastPage, from, to |
| Simple | select (one extra row) | hasMorePages |
| Cursor | select | nextCursor, previousCursor |

- **Cursor ordering:** cursor pagination needs a deterministic order; the key
  is always appended.
- **Provider support:** collection sources do not support cursors.
- **Transforms:** cursors are computed before `Transform` runs, so transforms
  may drop the ordering columns.

```php
$page = $data->query($source, ['cursor' => $previous->pagination->nextCursor]);
```

## Measured

`benchmarks/run.php` was run with PHP 8.5, in-memory SQLite and 10,000
subjects. Numbers vary by machine.

| Case | Per call | Queries |
| --- | --- | --- |
| Page 1, page-number mode | 1.16 ms | 2 |
| Page 1, simple | 0.69 ms | 1 |
| Page 1, cursor | 0.74 ms | 1 |
| Page about 390 of 400 | 1.76 ms | 2 |
