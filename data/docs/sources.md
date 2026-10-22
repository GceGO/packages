# Sources

A source is declared once with `Source::define()` and typed definition
objects, the same convention as `Blueprint::define()` in gcego/blueprints.

```php
use Gcego\Data\Definitions\{Constraints, Filterables, Model, Name, Pagination, Search, Sort, Sortables};
use Gcego\Data\Filters\{Filter, Filterable};
use Gcego\Data\Filters\Operators\{Between, Equal, GreaterThan, GreaterThanOrEqual, In, LessThan};
use Gcego\Data\Filters\Types\{Date, Decimal, Integer, StringType};
use Gcego\Data\Source;

return Source::define(
    Name::make('employees'),
    Model::make(Employee::class),
    Constraints::make([
        Filter::make('is_active', true),
    ]),
    Filterables::make([
        Filterable::make('age', [Integer::make(), GreaterThanOrEqual::make(), LessThan::make()]),
        Filterable::make('salary', [Decimal::make(), GreaterThan::make(), Between::make()]),
        Filterable::make('status', [StringType::make(), Equal::make(), In::make()]),
        Filterable::make('created_at', [Date::make(), Between::make()]),
    ]),
    Search::make(['name', 'email']),
    Sort::make('created_at', 'desc'),
    Sortables::make(['name', 'created_at']),
    Pagination::make(25),
);
```

## Origins

Exactly one is required:

| Definition | Provider | Records |
| --- | --- | --- |
| `Model::make(Employee::class)` | `eloquent` | models |
| `DatabaseTable::make('report_rows')` | `query` | arrays |
| `BuilderFactory::make(fn () => DB::table(...)->join(...))` | `query` (read-only) | arrays |
| `Items::make(fn () => config('hospital.wards'))` | `collection` | the items |
| `Provider::make('reports')` | a custom provider | provider-defined |

`Connection::make('archive')` chooses a connection. Table, builder and
collection sources may declare `Key::make('code')` (default `id`) and table
or builder sources `Columns::make([...])`.

## Definitions

| Definition | Purpose |
| --- | --- |
| `Name::make('employees')` | name in errors, diagnostics, the Gate ability and cache keys (default: the model's snake-case plural or the table) |
| `Constraints::make([Filter::make(...), Query::make(...)])` | trusted, mandatory conditions ([queries](queries.md)) |
| `Filterables::make([Filterable::make(...), CustomFilter::make(...)])` | request filters ([filtering](filtering.md)) |
| `Search::make([...])` | searchable fields ([searching](searching.md)) |
| `Sort::make(...)`, `Sortables::make([...])` | default and request ordering ([sorting](sorting.md)) |
| `Pagination::make(25)` | paging ([pagination](pagination.md)) |
| `Relations::make([...])`, `Includables::make([...])`, `Aggregates::make([...])` | relationships ([relationships](relationships.md)) |
| `Transform::only([...])` | output shape ([queries](queries.md#transformations)) |
| `Writable::make([...])`, `BeforeSave::make(...)` | writes ([mutations](mutations.md)) |
| `TenantScoped::make('company_id')` | tenant isolation ([tenancy](tenancy.md)) |
| `FieldSecurity::make([FieldAccess::...])` | field-level security ([security](security.md)) |
| `Cached::make(ttl: 300)` | result caching ([caching](caching.md)) |
| `Limits::make(...)` | execution limits ([performance](performance.md)) |

## Rules

- **Definition time** (`Source::define()`): each definition class at most
  once; exactly one origin; every definition validates its own arguments
  (field names, operators against value types, relation depth, page sizes).
  Problems throw `InvalidSourceException` at once.
- **Compile time** (first use, or `Data::inspect()`): the model class must
  exist and be an Eloquent model, cross-definition rules (a writable tenant
  column, a cached source without a name, Columns on a model source), and the
  provider's checks (relation paths exist, cursor ordering).
- **No I/O:** defining or compiling never queries the database; model classes
  are autoloaded only when compiled.
- **Immutable:** `$source->with(Pagination::make(50))` and
  `$source->without(Search::class)` return copies.
- **Closures** (`Query::make`, `BuilderFactory`, `Items`, relation
  constraints, `Transform::make`, `BeforeSave`) are trusted application code.
  `normalize()`, diagnostics and cache keys never contain them.

## Class-based sources

```php
final class EmployeesSource implements SourceProvider
{
    public function __construct(private CompanySettings $settings) {}

    public function source(): Source
    {
        return Source::define(
            Name::make('employees'),
            Model::make(Employee::class),
            Pagination::make($this->settings->pageSize()),
        );
    }
}

Data::query(EmployeesSource::class, $request);   // resolved through the container
```

Every `Data` method accepts a `Source`, a `SourceProvider` instance or a
`SourceProvider` class name (checked before the container instantiates it).
`source()` must stay pure: no queries, and no per-request state that should
be a `QueryContext` constraint.

## Caching definitions

Sources are PHP values built at runtime: they are not serialized into cache
files. What can be cached is the *result* of a query (`Cached`, see
[caching](caching.md)). Closures are rebuilt naturally because the source is
rebuilt from code (a `SourceProvider` or a definition file) on each request.
