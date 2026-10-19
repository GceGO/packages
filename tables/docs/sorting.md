# Sorting

```php
new Text('name', 'Subject Name', new Property(Text::Sortable, true))
new Text('category.name', 'Category', new Property(Text::Sortable, true), new Property(Text::SortColumn, 'category_name'))  // a source column
new Table(..., new Sort('name'))                                     // default order (gcego/data Sort)
new Table(..., new Property(Table::MultiSort, true))                 // shift-click adds columns (up to 3)
```

## Behavior

| Topic | Details |
| --- | --- |
| Header buttons | Each click cycles ascending, then descending, then unsorted. The header has `aria-sort` and a screen-reader status. |
| Allowlist | Sorting is resolved through the column definitions. Only sortable, readable columns become gcego/data `Sortable` keys, and a forged key is refused. |
| Stable paging | gcego/data adds the primary key as a tie-breaker, so pagination is stable. |
| Resets | Changing the sort resets the page. |
| Relation paths | They cannot be sorted directly (a definition error); map them to a source column with `SortColumn`. |
