# API reference

All classes live under `Gcego\Data`. Every `$source` argument accepts a
`Source`, a compiled `SourceDefinition`, a `SourceProvider` instance or a
`SourceProvider` class name.

## Entry point: `DataManager` (final readonly, singleton; facade `Facades\Data`)

| Method | Notes |
| --- | --- |
| `query($source, QueryInput\|Request\|array\|null $input = null, ?QueryContext $context = null): DataResult` | authorized listing |
| `aggregate($source, Aggregation, $input = null, ?QueryContext = null): AggregateResult` | authorized aggregate in the database (filters and search from input) |
| `scopedQuery($source, $input = null, ?QueryContext = null): EloquentBuilder\|QueryBuilder` | the rows a request may see, unordered (trusted packages) |
| `find($source, string\|int $key, ?QueryContext = null, $input = null): RecordResult` | scoped, authorized; only `include` is read from input |
| `findOrFail(...): mixed` | throws `RecordNotFoundException` (404) |
| `create($source, Request\|array $input, ?QueryContext = null): MutationResult` | validated, authorized, tenant-stamped, atomic |
| `update($source, string\|int $key, Request\|array $input, ?QueryContext = null): MutationResult` | partial; record locked inside the scope |
| `delete($source, $key, ?QueryContext = null, bool $force = false)`, `forceDelete()`, `restore()` | soft delete for SoftDeletes models |
| `createMany()`, `updateMany()`, `deleteMany()` | all or nothing |
| `transaction(Closure, ?TransactionOptions = null): mixed` | rollback, deadlock retries, isolation |
| `invalidate(...$targets): void` | cached results of sources, model classes or `table:connection:name` |
| `definition($source): SourceDefinition` | compiled, cached per Source |
| `source(SourceProvider\|string $provider): Source` | resolves a provider (class names checked before instantiation) |
| `scope(QueryConstraint\|Closure, ?class-string $target = null): void` | application-wide mandatory scope |
| `provider(string $name, DataProvider\|class-string, bool $override = false): void` | custom provider |
| `inspect($source): DataReport` | never throws |
| `explain($source, $input = null, ?QueryContext = null, bool $includeBindings = false): array{sql, bindings, values?}` | no execution |

## Declaration: `Source` and `Definitions\`

| Class | Members |
| --- | --- |
| `Source` (final, immutable) | `static define(SourceConfiguration ...)`, `with(...)`, `without(class-string ...)`, `get(class)`, `has(class)`, `definitions()`, `name()`, `normalize()`; constant `Origins` |
| `Definitions\Name` | `make(string)` |
| `Definitions\Model` | `make(class-string<Model>)` |
| `Definitions\DatabaseTable` | `make(string $table)` |
| `Definitions\BuilderFactory` | `make(Closure $factory)` (read-only) |
| `Definitions\Items` | `make(Closure\|iterable)` |
| `Definitions\Provider` | `make(string $name)` (custom provider) |
| `Definitions\Connection`, `Key`, `Columns` | `make(string)`, `make(string)`, `make(list<string>)` |
| `Definitions\Constraints` | `make(list<Filter\|QueryConstraint>)`; public `filters`, `queries` |
| `Definitions\Filterables` | `make(list<Filterable\|CustomFilter>)`; public `filterables`, `custom` |
| `Definitions\Search` | `make(list<string> $fields, ?class-string<SearchStrategy> $strategy = null)` |
| `Definitions\Sort` | `make(string\|array<string, string> $field, string\|SortDirection $direction = 'asc')`; public `orders` |
| `Definitions\Sortables` | `make(array $columns)` (plain names or key => column); public `columns` |
| `Definitions\Pagination` | `make(?int $perPage, ?int $max)`, `simple()`, `cursor()`; public `perPage`, `mode`, `max` |
| `Definitions\Relations`, `Includables` | `make(array $relations)` (paths, or path => closure); public `paths` |
| `Definitions\Aggregates` | `make(list<RelationAggregate>)` |
| `Definitions\Transform` | `make(Closure\|RecordTransformer)`, `only(list)`, `except(list)`, `map(array)`, `resource(class-string<JsonResource>)`; `transform()` |
| `Definitions\Writable` | `make(array $fields, list<DataOperation> $operations = [Create, Update, Delete])`, `allows()`, `rules()` |
| `Definitions\BeforeSave` | `make(Closure, list<DataOperation> $operations = [Create, Update])` |
| `Definitions\TenantScoped` | `make(string $column = 'tenant_id')` |
| `Definitions\FieldSecurity` | `make(list<FieldAccess>)` |
| `Definitions\Cached` | `make(int $ttl = 300, CacheIsolation $isolation = User, ?string $store = null)` |
| `Definitions\Limits` | `make(?int $timeoutMs, ?int $maxOffset, ?int $maxItems)` |

## Filtering (`Filters\`)

| Class | Members |
| --- | --- |
| `Filters\Filterable` | `make(string $key, list<FilterValueType\|Operator\|FilterOption>)`; `operator(string)`, `operatorNames()`, `isRelation()`, `normalize()`; public `key`, `type`, `operators`, `column`, `options`, `label` |
| `Filters\Filter` | `make(string $field, $value = null, ?Operator $operator = null, ?FilterValueType $type = null)`, `operatorName()`, `normalize()` |
| `Filters\CustomFilter` | `make(string $key, Closure\|FilterHandler $handler, list $configuration)` (one type; Equal and/or In) |
| `Filters\Options`, `Label`, `Column` | `make(list)`, `make(string)`, `make(string)` |
| `Filters\Types\*` | `StringType`, `Integer`, `Decimal`, `Boolean`, `Date`, `DateTime`, `Time`, `Uuid`, `Json` (`make()`); `Enum::make(class-string<BackedEnum>)` |
| `Filters\Operators\*` | `Equal`, `NotEqual`, `GreaterThan`, `GreaterThanOrEqual`, `LessThan`, `LessThanOrEqual`, `Between`, `In`, `NotIn`, `Contains`, `NotContains`, `StartsWith`, `EndsWith`, `IsNull`, `IsNotNull`, `Has`, `DoesntHave` (`make()`) |
| `Query\Query` | `make(Closure\|QueryConstraint)` |
| `Relationships\RelationAggregate` | `count()`, `sum()`, `avg()`, `min()`, `max()`, `exists()`, `optional()` |
| `Security\FieldAccess` | `make(string $field, $read = null, $write = null)`, `hidden()`, `readOnly()`, `ability()` |

## Execution

| Class | Members |
| --- | --- |
| `Query\QueryInput` | untrusted; `fromArray()`, `fromRequest(Request, ?string $group)`, `none()`, `toArray()`, `isEmpty()` |
| `Query\QueryContext` | trusted; `for(?user)`, `system()`, `constrain(...)`, `withMetadata()`, `forTenant()`, `acrossTenants()`, `cacheScope()`, `withoutCache()`, `profiled()` |
| `Query\QueryResolver` | `resolve(SourceDefinition, QueryInput): QueryPlan` |
| `Query\QueryPlan` | validated filters, terms, sorts, includes, mode, perPage, page, cursor, applied, customFilters, aggregates, searchFields, timeoutMs, maxItems |
| `Query\QueryPipeline` | `run()`, `aggregate()`, `scoped()`, `constraints()`, `plan()`, `present()`, `transform()` |
| `Records\RecordResolver` | `find(SourceDefinition, key, QueryContext, ?QueryInput)` |
| `Mutations\MutationPipeline` | `create()`, `update()`, `delete()`, `restore()`, `batch()` |
| `Transactions\TransactionManager` | `run(Closure, ?TransactionOptions)`, `retryable()`, `static isolationStatement()` |
| `Transactions\TransactionOptions` | `connection`, `attempts`, `backoffMs`, `isolation` (`IsolationLevel`), `retryOn` |
| `Caching\QueryCache` | `applies()`, `remember()`, `invalidate(...)`, `invalidateSource()`, `dependencies()` |
| `Tenancy\TenantGuard`, `Tenancy\RelationScope` | tenant column and constraint of a call; tenant scope of related records |
| `Security\FieldGuard` | `unreadable()`, `unwritable()`, `static redact()` |
| `Performance\Profiler`, `Performance\Profile` | `measure()`; `count()`, `queryTimeMs()`, `duplicates()`, `suspectsNPlusOne()`, `toArray()` |
| `Compilation\SourceCompiler`, `Compilation\SourceDefinition` | `compile(Source)`; the compiled, typed form (`extensions()`, `toArray()`) |
| `Results\DataResult` | `items()`, `collection()`, `paginator()`, `isEmpty()`, `count()`, iteration, `toArray()`, JSON, `meta()`; public `source`, `provider`, `pagination`, `applied`, `durationMs`, `cache`, `profile` |
| `Results\PaginationResult` | `mode`, `perPage`, `count`, `hasMorePages`, `currentPage`, `total`, `lastPage`, `from`, `to`, `nextCursor`, `previousCursor`; `isFirstPage()`, `toArray()` |
| `Results\RecordResult` | `found()`, `record()`, `orFail()`; public `source`, `key`, `cache`, `durationMs` |
| `Results\MutationResult` | `changed()`, `toArray()`, JSON; public `source`, `operation`, `key`, `record`, `changes`, `original`, `durationMs` |

## Contracts

| Interface | Methods |
| --- | --- |
| `Contracts\SourceConfiguration` | `normalize(): array`; every top-level definition |
| `Contracts\SourceProvider` | `source(): Source` |
| `Contracts\FilterValueType` | `name()`, `cast()`, `describe()`, `ordered()`, `textual()`, `options()` |
| `Contracts\Operator` | `name()`, `arity()`, `supports(FilterValueType)`, `apply()`, `matches()` |
| `Contracts\RelationOperator` | `applyRelation(EloquentBuilder, string $relation, ?Closure $scope)` |
| `Contracts\FilterHandler` | `apply(EloquentBuilder\|QueryBuilder\|Collection, mixed $value, SourceDefinition)` |
| `Contracts\DataProvider` | `prepare(SourceDefinition)`, `fetch(SourceDefinition, QueryPlan, list<QueryConstraint>)` |
| `Contracts\ResolvesRecords` | `find(SourceDefinition, string\|int, list<QueryConstraint>, array $includes = [], list<RelationAggregate> $aggregates = [])` |
| `Contracts\SupportsMutations` | `writable()`, `connection()`, `lock()`, `create()`, `update()`, `delete()`, `restore()`, `keyOf()`, `attributesOf()` |
| `Contracts\SupportsAggregation`, `ProvidesScopedQueries`, `SupportsRelationships`, `SupportsCursorPagination`, `InspectsQueries` | provider capabilities |
| `Contracts\QueryConstraint` | `apply(EloquentBuilder\|QueryBuilder\|Collection, SourceDefinition)` |
| `Contracts\SearchStrategy` | `apply(EloquentBuilder\|QueryBuilder, list $fields, list $terms, SourceDefinition)` |
| `Contracts\RecordTransformer` | `transform(mixed): mixed` |
| `Contracts\DataAuthorizer` | `authorize(SourceDefinition, DataOperation, QueryContext, mixed $record = null)` |
| `Contracts\TenantResolver` | `tenant(): string\|int\|null` |
| `Contracts\BelongsToTenant` | `tenantColumn(): string` (models) |

## Aggregation (`Aggregation\`)

| Class | Members |
| --- | --- |
| `Aggregation` | `__construct(AggregateFunction $function = Count, ?string $column, ?string $groupBy, ?string $dateColumn, ?TimeBucket $bucket, ?DateTimeInterface $from, ?DateTimeInterface $to, int $limit = 100)`; `isGrouped()`, `between()` |
| `AggregateFunction` | `Count`, `Sum`, `Average` (avg), `Minimum` (min), `Maximum` (max) |
| `TimeBucket` | `Day`, `Week`, `Month`, `Year` |
| `AggregateResult` | public `value`, `groups`, `truncated`; `pairs()` |

Aggregation columns are plain fields of the source, wrapped by the grammar;
dates are bindings; grouped results are capped at `limit` (at most 1000).

## Exceptions (all implement `Exceptions\DataException`)

| Exception | HTTP | Cause |
| --- | --- | --- |
| `InvalidSourceException` | 500 | developer definition error |
| `InvalidQueryException`, `InvalidFilterException`, `InvalidSortException` | 400 | request input |
| `RecordNotFoundException` | 404 | missing or out of scope (generic message; `context()` names the source) |
| `MissingTenantException` | 403 | tenant-scoped source without a tenant (generic message; `context()` has the hint) |
| `QueryTimeoutException` | 503 | statement timeout (generic message; `context()` has the source and timeout) |
| `UnsupportedProviderException` | 500 | unknown provider or missing capability |
| `MutationException` | 500 | read-only source, undeclared operation, out-of-scope write (rolled back) |
| `LimitExceededException` | 500 | a collection source exceeded its item limit |

Invalid write input is `Illuminate\Validation\ValidationException` (422);
authorization failures are `Illuminate\Auth\Access\AuthorizationException` (403).

## Service provider

`DataServiceProvider` implements `Gcego\Core\Contracts\PackageInterface`
(`gcego/data`, requiring only gcego/core): the publish tag
`gcego-data-config`, singletons for the services above, the `Data` facade
alias, and cache invalidation on Eloquent model events.
