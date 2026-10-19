# Searching

```php
new Search('name', 'code');
new Search(['name', 'code']);
new Search('name', 'topics.name', 'topics.lessons.title');   // Eloquent relation paths
(new Search('title', 'body'))->using(FullTextSearch::class);  // a custom strategy
```

`?search=linear algebra`

## Processing

- **Input:** the term is trimmed. Empty means no search.
- **Limits:** input longer than `search.max_length` (200), more than
  `search.max_terms` (8) words, or control characters → 400.
- **Matching:** the input is split on whitespace. Each word must match at
  least one field (words AND, fields OR), inside one group AND-ed with the
  mandatory constraints.
- **Fields:** only the declared fields are searched. Requests cannot choose
  fields.

## The default strategy: `LikeSearch`

- **SQL:** `"column" like ? escape '!'`, with `%`, `_` and `!` escaped in the
  term. A search for `%` matches a literal percent sign.
- **Binding:** the term is always a bound parameter.

| Database | Case-insensitive? |
| --- | --- |
| PostgreSQL | yes: `ilike` is used |
| MySQL / MariaDB | follows the column collation (yes for the default `*_ci` collations) |
| SQLite | yes for ASCII |
| SQL Server | follows the collation |

Verification:

- **SQLite:** searches are executed against real data.
- **MySQL and PostgreSQL:** the generated SQL is checked; no queries run
  against those servers.

**Performance:** `%term%` cannot use B-tree indexes. For large tables, use a
custom strategy (full-text index, prefix matching, a search engine).

## Custom strategies

```php
final class FullTextSearch implements SearchStrategy
{
    public function apply(EloquentBuilder|QueryBuilder $query, array $fields, array $terms, SourceDefinition $source): void
    {
        $query->whereFullText($fields, implode(' ', $terms));
    }
}
```

The strategy is resolved from the container for each query. It receives the
nested group (so it can only narrow), the trusted fields and the validated
terms. `tests/Fixtures/StartsWithSearch.php` is a working example.

Collection sources use case-insensitive substring matching (`mb_stripos`) over
the declared fields.
