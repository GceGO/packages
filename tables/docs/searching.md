# Searching

Mark columns `Searchable`. The table then shows a search box (debounced,
350 ms by default) and gcego/data matches every term against those fields
with bound `LIKE` parameters.

```php
new Table(
    new Text('name', 'Subject Name', new Property(Text::Searchable, true)),
    new Text('category.name', 'Category', new Property(Text::Searchable, true)),        // whereHas('category')
    new Text('full_name', 'Name', new Property(Text::Searchable, ['first_name', 'last_name'])),
    new Search(placeholder: 'Find a subject', debounceMs: 250),                            // optional
)
```

## Behavior

| Topic | Details |
| --- | --- |
| Normalization | Terms are trimmed, whitespace is collapsed, and the length is capped at `gcego-tables.search.max_length` (100). Terms are split by gcego/data. |
| Resets | Changing the search resets the page and clears the selection. |
| What is searched | Only searchable columns the user may read (`Access`). A `Search` node without searchable columns is a definition error. |
| Source override | If the Source declares its own `Search`, that one is used. |
| Echoing | The active search shows as an indicator with a clear button. It is escaped. |

## Large tables

`LIKE '%term%'` cannot use B-tree indexes, and relation searches add a
`whereHas` subquery. For large tables, give the Source a full-text
`SearchStrategy` (gcego/data); `TableInspector` warns about relation searches.
