# API reference

All classes live under `Gcego\Data`.

## Entry point: `DataManager` (final readonly, singleton; facade `Facades\Data`)

| Method | Notes |
| --- | --- |
| `query(Source\|SourceDefinition, QueryInput\|Request\|array\|null $input = null, ?QueryContext $context = null): DataResult` | authorized listing |
| `aggregate(Source\|SourceDefinition, Aggregation, QueryInput\|Request\|array\|null $input = null, ?QueryContext = null): AggregateResult` | authorized aggregate in the database (filters and search from input; pagination ignored) |
| `find(Source\|SourceDefinition, string\|int $key, ?QueryContext, QueryInput\|Request\|array\|null $input = null): RecordResult` | scoped, authorized; only `include` is read from input |
| `findOrFail(...): mixed` | throws `RecordNotFoundException` (404) |
| `definition(Source\|SourceDefinition): SourceDefinition` | compiled, cached per Source |
| `scope(QueryConstraint\|Closure, ?class-string $target = null): void` | application-wide mandatory scope |
| `provider(string $name, DataProvider\|class-string, bool $override = false): void` | |
| `inspect(Source\|SourceDefinition): DataReport` | never throws |
| `explain(Source\|SourceDefinition, $input = null, ?QueryContext = null, bool $includeBindings = false): array{sql, bindings, values?}` | no execution |
| `create(Source\|SourceDefinition, Request\|array $input, ?QueryContext = null): MutationResult` | validated, authorized, tenant-stamped, atomic |
| `update(Source\|SourceDefinition, string\|int $key, Request\|array $input, ?QueryContext = null): MutationResult` | partial; record locked inside the scope |
| `delete(..., ?QueryContext = null, bool $force = false)`, `forceDelete()`, `restore()` | soft delete for SoftDeletes models |
| `createMany()`, `updateMany()`, `deleteMany()` | all or nothing |
| `transaction(Closure, ?TransactionOptions = null): mixed` | rollback, deadlock retries, isolation |
| `invalidate(Source\|SourceDefinition\|string ...$targets): void` | cached results of sources, model classes or `table:connection:name` |

## Definition

| Class | Members |
| --- | --- |
| `Source` (final, immutable) | constants `Name`, `Model`, `Provider`, `Table`, `Connection`, `Key`, `Columns`, `Builder`, `Items`, `Query`, `Filters`, `Search`, `Sort`, `Pagination`, `Relationships`; `static model()`, `table()`, `builder()`, `collection()`; `name()`, `get()`, `has()`, `properties()`, `configurations()`, `configuration()`, `hasConfiguration()`, `withProperty()`, `withConfigurations()`, `named()`, `validate()`, `normalize()` |
| `Property` | `__construct(string $key, mixed $value = null)` |
| `Filters\Filter` | `__construct(string $field, $value = null, FilterOperator\|CustomOperator\|string $operator = Equals)`, `operatorName()` |
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
| `Support\Callback` | wrapper for trusted closures in properties; normalized to a placeholder |
| `Definition\SourceDefinition` | compiled, typed form; `extensions()`, `toArray()` |
| `Definition\SourceCompiler` | `compile(Source)` |

## Execution

| Class | Members |
| --- | --- |
| `Query\QueryInput` | untrusted; `fromArray()`, `fromRequest(Request, ?string $group)`, `none()`, `toArray()`, `isEmpty()` |
| `Query\QueryContext` | trusted; `for(?user)`, `system()`, `constrain(...)`, `withMetadata()`, `forTenant()`, `acrossTenants()`, `cacheScope()`, `withoutCache()`, `profiled()`; public `user`, `constraints`, `authorize`, `metadata`, `tenant`, `acrossTenants`, `cacheScope`, `profile`, `cache` |
| `Query\QueryResolver` | `resolve(SourceDefinition, QueryInput): QueryPlan` |
| `Query\QueryPlan` | validated filters, terms, sorts, includes, mode, perPage, page, cursor, applied, customFilters, aggregates, searchFields, timeoutMs, maxItems |
| `Query\QueryPipeline` | `run()`, `aggregate()`, `scoped()`, `constraints()`, `plan()`, `present()`, `transform()` |
| `Mutations\MutationPipeline` | `create()`, `update()`, `delete()`, `restore()`, `batch()` |
| `Transactions\TransactionManager` | `run(Closure, ?TransactionOptions)`, `retryable()`, `static isolationStatement()` |
| `Transactions\TransactionOptions` | `connection`, `attempts`, `backoffMs`, `isolation` (`IsolationLevel`), `retryOn` |
| `Caching\QueryCache` | `applies()`, `remember()`, `invalidate(...)`, `invalidateSource()`, `dependencies()` |
| `Tenancy\TenantGuard` | `column()`, `tenant()`, `constraint()` |
| `Security\FieldGuard` | `unreadable()`, `unwritable()`, `static redact()` |
| `Performance\Profiler` | `measure()`; `Performance\Profile`: `count()`, `queryTimeMs()`, `duplicates()`, `suspectsNPlusOne()`, `toArray()` |
| `Records\RecordResolver` | `find(SourceDefinition, key, QueryContext, ?QueryInput)` |
| `Results\DataResult` | `items()`, `collection()`, `paginator()`, `isEmpty()`, `count()`, iteration, `toArray()`, JSON, `meta()`; public `source`, `provider`, `pagination`, `applied`, `durationMs`, `cache`, `profile` |
| `Results\MutationResult` | `changed()`, `toArray()`, JSON; public `source`, `operation`, `key`, `record`, `changes`, `original`, `durationMs` |
| `Results\PaginationResult` | `mode`, `perPage`, `count`, `hasMorePages`, `currentPage`, `total`, `lastPage`, `from`, `to`, `nextCursor`, `previousCursor`; `isFirstPage()`, `toArray()` |
| `Results\RecordResult` | `found()`, `record()`, `orFail()`; public `source`, `key`, `cache`, `durationMs` |

## Declarations added for writes, security, caching and limits

| Class | Members |
| --- | --- |
| `Mutations\Writable` | `__construct(array $fields, list<DataOperation> $operations = [Create, Update, Delete])`, `allows()`, `rules()` |
| `Mutations\BeforeSave` | `__construct(Closure $callback, list<DataOperation> $operations = [Create, Update])` |
| `Security\FieldAccess` | `__construct(string $field, $read = null, $write = null)`, `static hidden()`, `readOnly()`, `ability()` |
| `Security\DataOperation` | `ViewAny`, `View`, `Create`, `Update`, `Delete`, `Restore`, `ForceDelete`; `isWrite()`, `isRecordLevel()` |
| `Tenancy\TenantScoped` | `__construct(string $column = 'tenant_id')` |
| `Caching\Cached` | `__construct(int $ttl = 300, CacheIsolation $isolation = User, ?string $store = null)` |
| `Caching\CacheIsolation` | `User`, `Tenant`, `Shared` |
| `Performance\Limits` | `__construct(?int $timeoutMs, ?int $maxOffset, ?int $maxItems)` |
| `Relationships\RelationAggregate` | `static count()`, `sum()`, `avg()`, `min()`, `max()`, `exists()`, `optional()` |
| `Filters\CustomFilter` | `__construct(string $key, Closure\|FilterHandler $handler, ValueType $type = String, bool $multiple = false, array $options = [], ?string $label = null)` |
| `Filters\Filterable` | also `static text()`, `enum()`, `relation()`, `operator(string)`, `operatorNames()` |
| `Filters\FilterOperator` | also `Contains`, `NotContains`, `StartsWith`, `EndsWith`, `Has`, `DoesntHave`; `isText()`, `isRelation()`, `static comparisons()` |
| `Transform\Transform` | also `static except()`, `map()`, `resource()` |

## Contracts

| Interface | Methods |
| --- | --- |
| `Contracts\DataProvider` | `prepare(SourceDefinition)`, `fetch(SourceDefinition, QueryPlan, list<QueryConstraint>)` |
| `Contracts\ResolvesRecords` | `find(SourceDefinition, string\|int, list<QueryConstraint>, array $includes = [], list<RelationAggregate> $aggregates = [])` |
| `Contracts\SupportsMutations` | `writable()`, `connection()`, `lock()`, `create()`, `update()`, `delete()`, `restore()`, `keyOf()`, `attributesOf()` |
| `Contracts\SupportsAggregation`, `Contracts\ProvidesScopedQueries` | `aggregate()`, `scoped()` |
| `Contracts\CustomOperator` | `name()`, `arity()`, `apply(EloquentBuilder\|QueryBuilder, string $column, mixed $value)`, `matches(mixed $actual, mixed $value)` |
| `Contracts\FilterHandler` | `apply(EloquentBuilder\|QueryBuilder\|Collection, mixed $value, SourceDefinition)` |
| `Contracts\TenantResolver` | `tenant(): string\|int\|null` |
| `Contracts\BelongsToTenant` | `tenantColumn(): string` (models) |
| `Contracts\SupportsRelationships`, `Contracts\SupportsCursorPagination` | capability markers |
| `Contracts\InspectsQueries` | `explain(SourceDefinition, QueryPlan, list<QueryConstraint>)` |
| `Contracts\QueryConstraint` | `apply(EloquentBuilder\|QueryBuilder\|Collection, SourceDefinition)` |
| `Contracts\SearchStrategy` | `apply(EloquentBuilder\|QueryBuilder, list $fields, list $terms, SourceDefinition)` |
| `Contracts\RecordTransformer` | `transform(mixed): mixed` |
| `Contracts\DataAuthorizer` | `authorize(SourceDefinition, DataOperation, QueryContext, mixed $record = null)` |
| `Contracts\SourceConfiguration` | `normalize(): array` for Source configuration nodes |

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
| `MissingTenantException` | 403 | tenant-scoped source without a current tenant |
| `MutationException` | 500 | read-only source, undeclared operation, out-of-scope write (rolled back) |
| `QueryTimeoutException` | 503 | statement timeout exceeded |
| `LimitExceededException` | 500 | a collection source exceeded its item limit |

Invalid write input is `Illuminate\Validation\ValidationException` (422).

Authorization failures are `Illuminate\Auth\Access\AuthorizationException`
(403).

## Service provider

`DataServiceProvider` implements `Gcego\Core\Contracts\PackageInterface`
(`gcego/data`, requiring only gcego/core):

- the publish tag `gcego-data-config`;
- singletons for the services above;

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
