# Searching

```php
Search::make(['name', 'email']);
Search::make(['name', 'department.name', 'reports.department.name']);   // Eloquent relation paths
Search::make(['title', 'body'], FullTextSearch::class);                  // a custom strategy
```

`?search=linear algebra`

## Processing

- **Input:** the term is trimmed; empty means no search.
- **Limits:** input longer than `search.max_length` (200), more than
  `search.max_terms` (8) words, or control characters → 400. A source
  searches at most 20 fields, and relation paths cross at most three
  relations.
- **Matching:** each word must match at least one field (words AND, fields
  OR), inside one group AND-ed with the mandatory constraints.
- **Fields:** only the declared fields; requests cannot choose fields. Fields
  the caller may not read ([field security](security.md#field-level-security))
  are left out of their search.
- **Relations:** relation paths use `whereHas` at each level, limited to the
  call's tenant for tenant-owned related models.

## The default strategy: `LikeSearch`

- **SQL:** `"column" like ? escape '!'`, with `%`, `_` and `!` escaped, so a
  search for `%` matches a literal percent sign. The term is always bound.

| Database | Case-insensitive? |
| --- | --- |
| PostgreSQL | yes: `ilike` is used |
| MySQL / MariaDB | follows the column collation (yes for the default `*_ci` collations) |
| SQLite | yes for ASCII |
| SQL Server | follows the collation |

**Performance:** `%term%` cannot use B-tree indexes. For large tables, use a
full-text, prefix or search-engine strategy.

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

The strategy is resolved from the container per query and receives the
nested group (so it can only narrow), the trusted fields and the validated
terms. Collection sources use case-insensitive substring matching.
