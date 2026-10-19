# API reference

All classes live under `Gcego\Data`.

## Entry point: `DataManager` (final readonly, singleton)

| Method | Notes |
| --- | --- |
| `query(Source\|SourceDefinition, QueryInput\|Request\|array\|null $input = null, ?QueryContext $context = null): DataResult` | authorized listing |
| `aggregate(Source\|SourceDefinition, Aggregation, QueryInput\|Request\|array\|null $input = null, ?QueryContext = null): AggregateResult` | authorized aggregate in the database (filters and search from input; pagination ignored) |
| `find(Source\|SourceDefinition, string\|int $key, ?QueryContext, QueryInput\|Request\|array\|null $input = null): RecordResult` | scoped, authorized; only `include` is read from input |
| `findOrFail(...): mixed` | throws `RecordNotFoundException` (404) |
| `definition(Source\|SourceDefinition): SourceDefinition` | compiled, cached per Source |
| `sourceOf(BlueprintInterface): ?Source` | the blueprint's `source` part |
| `scope(QueryConstraint\|Closure, ?class-string $target = null): void` | application-wide mandatory scope |
| `provider(string $name, DataProvider\|class-string, bool $override = false): void` | |
| `inspect(Source\|SourceDefinition): DataReport` | never throws |
| `explain(Source\|SourceDefinition, $input = null, ?QueryContext = null, bool $includeBindings = false): array{sql, bindings, values?}` | no execution |

## Definition

| Class | Members |
| --- | --- |
| `Source` (final, Component, BlueprintPart) | constants `Model`, `Provider`, `Table`, `Connection`, `Key`, `Columns`, `Builder`, `Items`, `Query`, `Filters`, `Search`, `Sort`, `Pagination`, `Relationships`; `static model()`, `table()`, `builder()`, `collection()`; `blueprintRole()` |
| `Filters\Filter` | `__construct(string $field, $value = null, FilterOperator\|string $operator = Equals)` |
| `Filters\Filterable` | `__construct(string $key, FilterOperator\|list $operators = [Equals, In], ValueType $type = String, ?string $column = null, array $options = [], ?string $label = null)`, `allows()` |
| `Filters\FilterOperator` | `Equals` (eq), `NotEquals` (neq), `GreaterThan` (gt), `GreaterThanOrEqual` (gte), `LessThan` (lt), `LessThanOrEqual` (lte), `In` (in), `NotIn` (not_in), `Between` (between), `Null` (null), `NotNull` (not_null) |
| `Filters\ValueType` | `String`, `Integer`, `Decimal`, `Boolean`, `Date`, `DateTime`; `cast()` |
| `Search\Search` | `__construct(string\|list ...$fields)`, `using(class-string<SearchStrategy>)`, `strategy()` |
| `Search\LikeSearch` | the default `SearchStrategy`; `static escape()` |
| `Sorting\Sort` | `__construct(string $field, SortDirection\|string $direction = 'asc')`, `static asc()`, `static desc()` |
| `Sorting\Sortable` | `__construct(string\|array<string,string> ...$keys)`, `static map()` |
| `Sorting\SortDirection` | `Ascending`, `Descending` |
| `Pagination\Paginate` | `__construct(?int $perPage = null, PaginationMode $mode = Pages, ?int $max = null)`, `static simple()`, `static cursor()` |
| `Pagination\PaginationMode` | `Pages`, `Simple`, `Cursor` |
| `Relationships\With`, `Relationships\Includable` | `__construct(string\|array ...$relations)` |
| `Query\Query` | `__construct(Closure\|QueryConstraint)` |
| `Transform\Transform` | `__construct(Closure\|RecordTransformer)`, `static only(string ...)` |
| `Support\Callback` | `RuntimeValue` wrapper for closures in properties |
| `Definition\SourceDefinition` | compiled, typed form; `extensions()`, `toArray()` |
| `Definition\SourceCompiler` | `compile(Source)` |

## Execution

| Class | Members |
| --- | --- |
| `Query\QueryInput` | untrusted; `fromArray()`, `fromRequest(Request, ?string $group)`, `none()`, `toArray()`, `isEmpty()` |
| `Query\QueryContext` | trusted; `for(?user)`, `system()`, `constrain(...)`, `withMetadata()`; public `user`, `constraints`, `authorize`, `metadata` |
| `Query\QueryResolver` | `resolve(SourceDefinition, QueryInput): QueryPlan` |
| `Query\QueryPlan` | validated filters, terms, sorts, includes, mode, perPage, page, cursor, applied |
| `Query\QueryPipeline` | `run()`, `constraints()`, `transform()` |
| `Records\RecordResolver` | `find(SourceDefinition, key, QueryContext, ?QueryInput)` |
| `Results\DataResult` | `items()`, `collection()`, `paginator()`, `isEmpty()`, `count()`, iteration, `toArray()`, JSON; public `source`, `provider`, `pagination`, `applied`, `durationMs` |
| `Results\PaginationResult` | `mode`, `perPage`, `count`, `hasMorePages`, `currentPage`, `total`, `lastPage`, `from`, `to`, `nextCursor`, `previousCursor`; `isFirstPage()`, `toArray()` |
| `Results\RecordResult` | `found()`, `record()`, `orFail()`; public `source`, `key` |

## Contracts

| Interface | Methods |
| --- | --- |
| `Contracts\DataProvider` | `prepare(SourceDefinition)`, `fetch(SourceDefinition, QueryPlan, list<QueryConstraint>)` |
| `Contracts\ResolvesRecords` | `find(SourceDefinition, string\|int, list<QueryConstraint>, array $includes = [])` |
| `Contracts\SupportsRelationships`, `Contracts\SupportsCursorPagination` | capability markers |
| `Contracts\InspectsQueries` | `explain(SourceDefinition, QueryPlan, list<QueryConstraint>)` |
| `Contracts\QueryConstraint` | `apply(EloquentBuilder\|QueryBuilder\|Collection, SourceDefinition)` |
| `Contracts\SearchStrategy` | `apply(EloquentBuilder\|QueryBuilder, list $fields, list $terms, SourceDefinition)` |
| `Contracts\RecordTransformer` | `transform(mixed): mixed` |
| `Contracts\DataAuthorizer` | `authorize(SourceDefinition, DataOperation, QueryContext, mixed $record = null)` |
| `Contracts\SourceConfiguration` | marker for Source configuration nodes |

## Providers and security

- **Providers:** `Providers\EloquentProvider`, `QueryBuilderProvider` and
  `CollectionProvider`, plus `Providers\ProviderRegistry` (`register()`,
  `has()`, `get()`, `for()`, `names()`).
- **Security:**
  - `Security\GateDataAuthorizer` (default);
  - `Security\ScopeRegistry` (`register()`, `for()`, `count()`);
  - `Security\DataOperation` (`ViewAny`, `View`).

## Diagnostics

`Diagnostics\QueryInspector` has `inspect()`, `explain()` and
`static capabilities()`. `Diagnostics\DataReport` has public `source` and
`diagnostics`, plus `check()`, `isHealthy()` and `toArray()`.
`Diagnostics\DataDiagnostic` carries `check`, `level`, `message` and `details`.

The checks are `source`, `provider`, `authorization`, `pagination` and
`sorting`.

## Configuration (`config/gcego-data.php`)

| Key | Default |
| --- | --- |
| `pagination.default_per_page` | `25` |
| `pagination.max_per_page` | `100` |
| `search.max_length` | `200` |
| `search.max_terms` | `8` |
| `filters.max_values` | `100` |
| `sorting.max_fields` | `3` |
| `sorting.stable` | `true` |
| `queries.strict` | `true` |
| `authorization.ability` | `gcego-data.query` |

## Exceptions (all implement `Exceptions\DataException`)

| Exception | HTTP | Cause |
| --- | --- | --- |
| `InvalidSourceException` | 500 | developer definition error |
| `InvalidQueryException`, `InvalidFilterException`, `InvalidSortException` | 400 | request input |
| `RecordNotFoundException` | 404 | missing or out of scope |
| `UnsupportedProviderException` | 500 | unknown provider or missing capability |

Authorization failures are `Illuminate\Auth\Access\AuthorizationException`
(403).

## Service provider

`DataServiceProvider` implements `Gcego\Core\Contracts\PackageInterface`
(`gcego/data`, requiring gcego/core, with gcego/runtime optional):

- the publish tag `gcego-data-config`;
- singletons for the services above;
- `Integration\SourceRenderer` for the runtime, when gcego/runtime is installed.

## Aggregation (`Aggregation\`)

| Class | Members |
| --- | --- |
| `Aggregation` | `__construct(AggregateFunction $function = Count, ?string $column, ?string $groupBy, ?string $dateColumn, ?TimeBucket $bucket, ?DateTimeInterface $from, ?DateTimeInterface $to, int $limit = 100)`; `isGrouped()`, `between()` |
| `AggregateFunction` | `Count`, `Sum`, `Average` (avg), `Minimum` (min), `Maximum` (max) |
| `TimeBucket` | `Day` (2026-03-01), `Week` (2026-W09, the database's week numbering), `Month` (2026-03), `Year` |
| `AggregateResult` | public `value`, `groups` (list of key/value), `truncated`; `pairs()` |
| `Contracts\SupportsAggregation` | `aggregate(SourceDefinition, QueryPlan, list<QueryConstraint>, Aggregation): AggregateResult` (builder and collection providers) |

**Column rules:** columns are plain fields of the source (FieldName rules,
no relation paths), wrapped by the grammar.

**Date rules:** dates are bindings, and buckets are computed in the stored
time zone.

**Group limit:** grouped results are capped at `limit` (at most 1000);
`truncated` reports a cut.
