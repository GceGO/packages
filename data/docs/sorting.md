# Sorting

```php
new Sort('name');                 // default ordering, ascending
new Sort('created_at', 'desc');
Sort::desc('level');
new Sortable('name', 'level', ['newest' => 'created_at']);   // request keys => columns
```

`?sort=-newest,name`

- **Defaults:** `Sort` nodes apply only when the request asks for no sort. They
  apply in declaration order.
- **Allowlist:** requested keys must be `Sortable`; the column comes from the
  definition. Unknown keys return 400 in strict mode, and are ignored (falling
  back to the defaults) otherwise.
- **Limit:** at most `sorting.max_fields` (3) requested fields.
- **Columns:** sortable columns are the source's own columns. Relation paths
  are not supported for sorting.

## Stable ordering

When `sorting.stable` is true (the default), the unique key is appended as
the last ordering:

- Eloquent: the model's qualified key, for example `"courses"."code"` for a
  custom key;
- query sources: `Source::Key`;
- collections: the key field.

Rows with equal sort values therefore keep the same order on every page, and
pages never overlap or skip records. Cursor pagination always appends the key.

```sql
order by "subjects"."level" desc, "subjects"."id" asc
```
