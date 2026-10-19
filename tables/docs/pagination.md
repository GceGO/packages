# Pagination

Pagination always happens in the database (`LIMIT`/`OFFSET` or a keyset
cursor, via gcego/data).

```php
new Pagination(25)                                                   // page numbers (one COUNT per request)
new Pagination(50, options: [25, 50, 100])                           // sizes offered to users
new Pagination(50, mode: PaginationMode::Simple)                     // previous/next, no COUNT
new Pagination(50, mode: PaginationMode::Cursor)                     // keyset; needs a default Sort
```

## Limits

| Setting | Default |
| --- | --- |
| `gcego-tables.pagination.max_per_page` | 100 (gcego/data applies its own cap too) |
| `gcego-tables.pagination.options` | sizes offered when the table sets none |

A requested page size must be one of the offered sizes.

## Behavior

| Topic | Details |
| --- | --- |
| Out of range | A page past the end is moved to the last page. |
| Markup | `<nav aria-label="Pagination">` with `aria-current="page"`, previous/next buttons and a "Showing X to Y of Z" summary where totals exist. |
| Preference | The chosen page size is a user preference. |
| Source override | When the Source declares its own pagination, the Source wins. |
