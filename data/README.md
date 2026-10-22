# gcego/data

Universal data access and query engine for GCEGO Studio: the one standardized,
secure and reusable way every GCEGO package reads and writes application data,
whether the application runs a school, a hospital, a business, a government
agency or a store. Declare a data source once; gcego/data handles querying,
filtering, searching, sorting, pagination, relationships, validated writes,
transactions, caching, tenant isolation, field-level security and
performance limits. Application code doesn't need repositories, query
services or filter controllers.

```php
use App\Models\Subject;
use Gcego\Data\Source;

new Source(Subject::class);
```

```php
use Gcego\Data\Property;

new Source(
    new Property(Source::Model, Subject::class),
);
```

```php
use Gcego\Data\Filters\Filter;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Sorting\Sort;

new Source(
    Subject::class,

    new Filter('is_published', true),
    new Sort('name', 'asc'),
    new Paginate(25),
);
```

```php
use Gcego\Data\Query\Query;

new Source(
    Subject::class,

    new Query(
        fn ($query) => $query
            ->where('is_published', true)
            ->with('topics'),
    ),
);
```

A `Source` is a definition only. Constructing one never touches the database.
Queries run when you execute it:

```php
use Gcego\Data\Facades\Data;

Route::get('/subjects', fn (Request $request) => Data::query($source, $request));
Route::get('/subjects/{id}', fn (string $id) => Data::findOrFail($source, $id));
```

The `Data` facade (auto-aliased) proxies the `Gcego\Data\DataManager`
singleton, which you can also inject.

`GET /subjects?filter[level]=2&search=bio&sort=-name&page=2&per_page=50&include=topics`
returns JSON. Invalid input is rejected with **400**, a missing or
out-of-scope record with **404**, and an unauthorized read with **403**
(see `tests/Feature/HttpEndpointTest.php`).

## Requirements

- PHP 8.3+, Laravel 13 (`illuminate/database`, `pagination`, `auth`, `http`).
- `gcego/core` ^1.0, its only GCEGO dependency. gcego/data is the shared
  data engine every other GCEGO package builds on, whatever the domain
  (school, hospital, business, government, e-commerce).

## Installation

```bash
composer require gcego/data
php artisan vendor:publish --tag=gcego-data-config   # optional
```

The service provider is auto-discovered. It is the `gcego/data` package of
gcego/core, with capabilities `data`, `data.eloquent`, `data.query` and
`data.collection`.

## Authorization: required before data is returned

Every read is authorized, and authorization fails closed:

- **Eloquent sources** use the model's policy: `viewAny` for listings and
  `view` for each resolved record. A model without a policy is denied.
- **Query-builder and collection sources** check the `gcego-data.query` Gate
  ability.
- **Trusted code without a user** (jobs, console commands) passes
  `QueryContext::system()`.

See [security](docs/security.md).

## What a source can declare

| Need | Declaration | Request parameter |
| --- | --- | --- |
| Mandatory condition | `new Filter('is_published', true)`, `new Query(fn ($q) => ...)` | none: cannot be removed |
| User filtering | `new Filterable('level', [FilterOperator::GreaterThanOrEqual], ValueType::Integer)` | `filter[level][gte]=2` |
| Search | `new Search('name', 'code', 'topics.name')` | `search=bio` |
| Default order | `new Sort('name')`, `Sort::desc('created_at')` | none |
| User sorting | `new Sortable('name', ['newest' => 'created_at'])` | `sort=-newest,name` |
| Pagination | `new Paginate(25)`, `Paginate::simple(50)`, `Paginate::cursor(50)` | `page`, `per_page`, `cursor` |
| Eager loading | `new With('topics')` | none |
| Optional includes | `new Includable('topics.lessons')` | `include=topics.lessons` |
| Text, enum, relation filters | `Filterable::text('name')`, `Filterable::enum('status', Status::class)`, `Filterable::relation('topics')` | `filter[name][contains]=alg` |
| Custom filters and operators | `new CustomFilter('overdue', fn ($q, bool $v) => ...)`, `new Filterable('tags', new JsonContains)` | `filter[overdue]=1` |
| Relationship aggregates | `RelationAggregate::count('topics')`, `RelationAggregate::sum('fees', 'amount')->optional()` | `include=fees_sum_amount` |
| Output shape | `Transform::only('id', 'name')`, `Transform::map([...])`, `Transform::resource(SubjectResource::class)` | none |
| Writes | `new Writable(['name' => 'required\|string'])`, `new BeforeSave(fn (...) => ...)` | `Data::create/update/delete()` |
| Tenant isolation | `new TenantScoped('company_id')`, or models implementing `BelongsToTenant` | none: enforced |
| Field security | `FieldAccess::hidden('ssn')`, `FieldAccess::ability('salary', read: 'viewSalary')` | none: enforced |
| Caching | `new Cached(ttl: 300)` on a named source | none |
| Execution limits | `new Limits(timeoutMs: 2000, maxOffset: 10000)` | none |

Providers: Eloquent models (`new Source(Model::class)`), tables and query
builders (`Source::table()`, `Source::builder()`), and in-memory collections
(`Source::collection()`).

## Documentation

- [Architecture](docs/architecture.md)
- [Sources](docs/sources.md)
- [Providers](docs/providers.md)
- [Queries and the pipeline](docs/queries.md)
- [Filtering](docs/filtering.md)
- [Searching](docs/searching.md)
- [Sorting](docs/sorting.md)
- [Pagination](docs/pagination.md)
- [Relationship aggregates and conditions](docs/relationships.md)
- [Mutations](docs/mutations.md)
- [Transactions](docs/transactions.md)
- [Query caching](docs/caching.md)
- [Tenant scoping](docs/tenancy.md)
- [Security](docs/security.md)
- [Performance](docs/performance.md)
- [Extensibility](docs/extensibility.md)
- [API reference](docs/api-reference.md)

## Development

```bash
composer test        # Pest: unit, integration (SQLite) and HTTP feature tests
composer analyse     # PHPStan level max with Larastan
composer format      # Pint
composer benchmark   # benchmarks/run.php [rows]
```

## License

MIT. See [LICENSE.md](LICENSE.md).
