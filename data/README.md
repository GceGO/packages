# gcego/data

The universal data access and query engine of **GCEGO Studio**: one
standardized, secure and reusable way for every GCEGO package to read and
write application data, whether the application runs a school, a hospital,
a business, a government agency or a store.

Declare a source once with typed definitions; gcego/data handles querying,
filtering, searching, sorting, pagination, relationships, validated writes,
transactions, caching, tenant isolation, field-level security and execution
limits. Application code needs no repositories, query services or filter
controllers.

```php
use App\Models\Employee;
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

A source is a definition only; defining it never touches the database. The
`Data` facade (auto-aliased, backed by the `Gcego\Data\DataManager`
singleton) executes it:

```php
use Gcego\Data\Facades\Data;

Route::get('/employees', fn (Request $request) => Data::query($employees, $request));
Route::get('/employees/{id}', fn (string $id) => Data::findOrFail($employees, $id));
Route::post('/employees', fn (Request $request) => response()->json(Data::create($employees, $request), 201));
```

`GET /employees?filter[age][gte]=30&search=ann&sort=-created_at&page=2&per_page=50`
returns JSON. Invalid input is **400** (**422** for writes), a missing or
out-of-scope record **404**, an unauthorized call **403**.

## Requirements

- PHP 8.3+, Laravel 13 (`illuminate/database`, `pagination`, `auth`, `http`, `validation`).
- `gcego/core` ^1.0: the only GCEGO dependency. gcego/data does not depend on
  registry, blueprints, components, runtime or any UI package.

## Installation

```bash
composer require gcego/data
php artisan vendor:publish --tag=gcego-data-config   # optional
```

The service provider is auto-discovered; it is the `gcego/data` package of
gcego/core.

## Declaring sources

| Need | Definition | Request |
| --- | --- | --- |
| Origin | `Model::make()`, `DatabaseTable::make()`, `BuilderFactory::make()`, `Items::make()`, `Provider::make()` | |
| Mandatory conditions | `Constraints::make([Filter::make(...), Query::make(fn ($q) => ...)])` | cannot be removed |
| User filters | `Filterables::make([Filterable::make('age', [Integer::make(), GreaterThanOrEqual::make()])])` | `filter[age][gte]=30` |
| Custom filters and operators | `CustomFilter::make('overdue', fn ($q, bool $v) => ..., [Boolean::make()])`, `Contracts\Operator` | `filter[overdue]=1` |
| Search | `Search::make(['name', 'department.name'])` | `search=ann` |
| Ordering | `Sort::make('created_at', 'desc')`, `Sortables::make(['name', 'newest' => 'created_at'])` | `sort=-newest,name` |
| Pagination | `Pagination::make(25)`, `Pagination::simple(50)`, `Pagination::cursor(50)` | `page`, `per_page`, `cursor` |
| Relationships | `Relations::make([...])`, `Includables::make([...])`, `Aggregates::make([RelationAggregate::count('reports')])` | `include=department` |
| Output shape | `Transform::only([...])`, `Transform::map([...])`, `Transform::resource(...)` | |
| Writes | `Writable::make(['name' => 'required\|string'])`, `BeforeSave::make(...)` | `Data::create/update/delete()` |
| Tenant isolation | `TenantScoped::make('company_id')`, or models implementing `BelongsToTenant` | enforced |
| Field security | `FieldSecurity::make([FieldAccess::hidden('ssn'), FieldAccess::ability('salary', read: 'viewSalary')])` | enforced |
| Caching | `Cached::make(ttl: 300)` (named sources) | |
| Execution limits | `Limits::make(timeoutMs: 2000, maxOffset: 10000)` | |

Class-based sources implement `Contracts\SourceProvider` and can be passed
by class name: `Data::query(EmployeesSource::class, $request)`.

## Security model

- **Fail-closed authorization:** model policies (`viewAny`, `view`, `create`,
  `update`, `delete`, `restore`, `forceDelete`) or the `gcego-data.query`
  Gate ability; missing policies deny. Trusted code without a user passes
  `QueryContext::system()`.
- **Tenant isolation** before any other condition, also for related records
  and cached results; no tenant means 403.
- **Allowlisted input only:** filter keys and operators, sort keys, includes;
  every value cast and bound; bounded page sizes, offsets, search and lists.
- **No leaks:** field-level security, generic public error messages, no SQL
  values outside debug mode.

See [security](docs/security.md) and [tenancy](docs/tenancy.md).

## Documentation

- [Architecture](docs/architecture.md)
- [Sources](docs/sources.md)
- [Providers](docs/providers.md)
- [Queries and the pipeline](docs/queries.md)
- [Filtering](docs/filtering.md)
- [Searching](docs/searching.md)
- [Sorting](docs/sorting.md)
- [Pagination](docs/pagination.md)
- [Relationships](docs/relationships.md)
- [Mutations](docs/mutations.md)
- [Transactions](docs/transactions.md)
- [Query caching](docs/caching.md)
- [Tenant scoping](docs/tenancy.md)
- [Security](docs/security.md)
- [Performance](docs/performance.md)
- [Extensibility](docs/extensibility.md)
- [API reference](docs/api-reference.md)
- [Migration guide](docs/migration.md)

## Development

```bash
composer test        # Pest: unit, integration (SQLite) and HTTP feature tests
composer analyse     # PHPStan level max with Larastan
composer format      # Pint
composer benchmark   # benchmarks/run.php [rows]
```

## License

MIT. See [LICENSE.md](LICENSE.md).
