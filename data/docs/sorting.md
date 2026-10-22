# Sorting

```php
Sort::make('created_at', 'desc');                       // default ordering
Sort::make(['level' => 'desc', 'name' => 'asc']);       // several defaults, in order
Sortables::make(['name', 'created_at']);                // request keys = columns
Sortables::make(['name', 'newest' => 'created_at', 'popular' => 'reports_count']);   // aliases
```

`?sort=-newest,name`

- **Defaults:** `Sort` applies only when the request sorts by nothing.
- **Allowlist:** requested keys must be declared in `Sortables`; the column
  comes from the definition. Unknown keys return 400 in strict mode, and are
  ignored (falling back to the defaults) otherwise.
- **Directions:** `asc` or `desc` in definitions; a `-` prefix in requests.
- **Limit:** at most `sorting.max_fields` (3) requested fields.
- **Columns:** the source's own columns, or a relation aggregate alias
  (`reports_count`); relation paths are rejected.
- **Field security:** sorting by a field the caller may not read is 400.

## Stable ordering

When `sorting.stable` is true (the default), the unique key is appended as
the last ordering (the model's qualified key, `Key::make()` for table and
collection sources), so rows with equal sort values keep the same order on
every page and pages never overlap or skip records. Cursor pagination always
appends the key.

```sql
order by "employees"."age" desc, "employees"."id" asc
```
