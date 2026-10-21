# gcego/data

Data access for GCEGO Admin Studio. Declare a data source once; the framework
handles querying, filtering, searching, sorting, pagination, eager loading,
scoped record lookup and authorization. Application code doesn't need
repositories, query services or filter controllers.

```php
use App\Models\Subject;
use Gcego\Data\Source;

new Source(Subject::class);
```

```php
use Gcego\Components\Properties\Property;

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
use Gcego\Data\DataManager;

Route::get('/subjects', fn (Request $request, DataManager $data) => $data->query($source, $request));
Route::get('/subjects/{id}', fn (string $id, DataManager $data) => $data->findOrFail($source, $id));
```

`GET /subjects?filter[level]=2&search=bio&sort=-name&page=2&per_page=50&include=topics`
returns JSON. Invalid input is rejected with **400**, a missing or
out-of-scope record with **404**, and an unauthorized read with **403**
(see `tests/Feature/HttpEndpointTest.php`).

## Requirements

- PHP 8.3+, Laravel 13 (`illuminate/database`, `pagination`, `auth`, `http`).
- `gcego/morfaw`, `gcego/blueprints` and `gcego/core` ^0.1.
- Optional: `gcego/runtime`. A `Source` inside a blueprint then renders as nothing.

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
| Output shape | `Transform::only('id', 'name')`, `new Transform(fn ($r) => ...)` | none |

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
- [Relationships](docs/relationships.md)
- [Security](docs/security.md)
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
