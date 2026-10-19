# Providers

## Eloquent (`eloquent`)

`new Source(Subject::class)`

- **Base query:** `$model->newQuery()`. Global scopes (tenancy, soft deletes,
  custom scopes) always apply; nothing removes them automatically. Opt in
  explicitly, in trusted code:
  `new Query(fn ($q) => $q->withTrashed())`.
- **Connection:** the model's own, unless `Source::Connection` is set.
- **Keys:** read from the model.
  - Integer keys accept digits only.
  - `HasUuids` and `HasUlids` models validate keys with the model's own rule.
  - String keys are any non-empty string up to 255 characters.
  - Malformed keys are "not found" without a query.
- **Records:** model instances. `$hidden`, `$visible`, casts and accessors apply
  when serialized.
- **Relation paths:** `topics.name` in a filter or search means "has a related
  topic whose name matches" (`whereHas`), so the relation's own scopes apply.
- **Columns:** no column selection (`Source::Columns` is rejected). Use
  `$hidden` or `Transform::only()`.

## Query builder (`query`)

`Source::table('subjects')` or `Source::builder(fn () => DB::table(...))`.

- **Factory:** it is trusted code, called once per execution, and its bindings
  are kept. It must return an `Illuminate\Database\Query\Builder`; an
  Eloquent builder is rejected, so use a model source with a `Query` node.
- **Rows:** associative arrays.
- **Not available:** models, global scopes, casts, `$hidden` and relationships.
  **Select only what you mean to expose** (`Source::Columns` or `select()`).
- **Fields:** used as written (`name`, `subjects.name`).
- **Key:** `Source::Key`, default `id`. It is used for lookups and as the
  final ordering.

| | Eloquent `Builder` | Query `Builder` |
| --- | --- | --- |
| Records | models | arrays |
| Global scopes, soft deletes | applied | not applicable |
| Relationships, `topics.name` paths | yes | no; dotted names are `table.column` |
| Hidden attributes | `$hidden` | select columns explicitly |

## Collection (`collection`)

`Source::collection(fn () => [...])` or `Source::collection($items)`.

- **Items:** arrays or objects, read with `data_get()`. The original collection
  is never modified.
- **Evaluation:** filters, search, sorting and pagination run in PHP over
  **all** items on every call. Use it for small, bounded data (settings, terms,
  enums, data already fetched), not large tables.
- **Comparison:** numbers numerically, booleans as booleans, everything else as
  case-sensitive strings.
- **Search:** case-insensitive.
- **Sorting:** stable.
- **Not supported:** cursor pagination and relationships, both rejected when
  the source is prepared.

## Custom providers

```php
final class ReportProvider implements DataProvider, ResolvesRecords
{
    public function prepare(SourceDefinition $source): void { /* validate, no I/O */ }

    public function fetch(SourceDefinition $source, QueryPlan $plan, array $constraints): AbstractPaginator|AbstractCursorPaginator { /* ... */ }

    public function find(SourceDefinition $source, string|int $key, array $constraints, array $includes = []): mixed { /* ... */ }
}

// A service provider's register() or boot():
$this->app->make(DataManager::class)->provider('reports', ReportProvider::class);

new Source(new Property(Source::Provider, 'reports'), new Paginate(20));
```

- **Contract:** apply `$constraints` first. They are mandatory, and the order
  is documented on `DataProvider`.
- **Request input:** use only the `QueryPlan`, never raw input.
- **State:** stay stateless, because providers are shared singletons.
- **Capabilities:** implement only the ones you support. The pipeline rejects
  cursor pagination, for example, when `SupportsCursorPagination` is missing.
