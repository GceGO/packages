# Providers

| | Eloquent (`Model`) | Query builder (`DatabaseTable`, `BuilderFactory`) | Collection (`Items`) |
| --- | --- | --- | --- |
| Records | models | arrays | the items |
| Authorization | model policies | `gcego-data.query` ability | `gcego-data.query` ability |
| Global scopes, soft deletes | applied | not applicable | not applicable |
| Relations, relation filters and search, aggregates | yes | no | no |
| Text, range and list filters | SQL | SQL | in PHP, same semantics |
| Pagination | pages, simple, cursor | pages, simple, cursor | pages, simple |
| Writes | yes | tables only | no |
| Hidden attributes | `$hidden`, `FieldSecurity` | select columns, `FieldSecurity` | `FieldSecurity` |

## Eloquent

`Model::make(Employee::class)`

- **Base query:** `$model->newQuery()`; global scopes always apply. Opt out
  explicitly in trusted code: `Query::make(fn ($q) => $q->withTrashed())`.
- **Connection:** the model's, unless `Connection::make()` is declared.
- **Keys:** integer keys accept digits only; `HasUuids`/`HasUlids` keys use
  the model's rule; string keys any non-empty string up to 255 characters.
  Malformed keys are "not found" without a query.
- **Relation paths:** `department.name` means "has a related department whose
  name matches" (`whereHas` per level, tenant-scoped for tenant-owned models).
- **Columns:** no column selection; use `$hidden`, `FieldSecurity` or
  `Transform`.

## Query builder

`DatabaseTable::make('report_rows')` or `BuilderFactory::make(fn () => DB::table(...))`.

- **Factory:** trusted code, called per execution. It must return an
  `Illuminate\Database\Query\Builder`; an Eloquent builder is rejected (use a
  model source with `Constraints::make([Query::make(...)])`).
- **No models:** no global scopes, casts, `$hidden` or relationships. **Select
  only what callers may see** (`Columns::make([...])` or `select()`).
- **Key:** `Key::make('code')`, default `id`.
- **Writes:** table sources only; builder factories are read-only.

## Collection

`Items::make(fn () => [...])` or `Items::make($items)`.

- **Evaluation:** every call loads every item (at most
  `performance.collection_max_items`, 10,000); keep it small and bounded.
- **Comparison:** numbers numerically, booleans as booleans, otherwise
  strings; text operators and search are case-insensitive.
- **Not supported:** cursor pagination, relationships, relation filters,
  aggregates and writes; rejected when the source is prepared.

## Custom providers

```php
final class ReportProvider implements DataProvider, ResolvesRecords
{
    public function prepare(SourceDefinition $source): void { /* validate, no I/O */ }

    public function fetch(SourceDefinition $source, QueryPlan $plan, array $constraints): AbstractPaginator|AbstractCursorPaginator { /* ... */ }

    public function find(SourceDefinition $source, string|int $key, array $constraints, array $includes = [], array $aggregates = []): mixed { /* ... */ }
}

Data::provider('reports', ReportProvider::class);       // a service provider's register() or boot()

Source::define(Provider::make('reports'), Pagination::make(20));
```

- **Contract:** apply `$constraints` first (they include the tenant scope);
  use only the `QueryPlan`, never raw input; stay stateless (providers are
  shared singletons); bind every value.
- **Capabilities:** implement only what you support (`ResolvesRecords`,
  `SupportsCursorPagination`, `SupportsAggregation`, `SupportsMutations`,
  `SupportsRelationships`, `InspectsQueries`); the pipeline rejects the rest.
