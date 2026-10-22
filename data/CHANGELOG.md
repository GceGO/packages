# Changelog

## Unreleased

### Breaking

- No longer depends on `gcego/components` or `gcego/runtime`. `Source` is a standalone immutable value object (`final class Source`), and `Gcego\Data\Property` replaces `Gcego\Components\Properties\Property`. `configurations()` returns a list; use `configuration(Filter::class)` and `hasConfiguration(Filter::class)`. `validate()` returns a list of messages. `Source::Name` replaces `Component::Name`; `named()` sets it.
- `SourceConfiguration` no longer extends a components contract; it declares `normalize(): array` itself. `Support\Callback` no longer implements `RuntimeValue`.
- Depends only on `gcego/core` ^1.0 (from Packagist). All `gcego/blueprints` and `gcego/registry` integration is removed (`BlueprintRole`/`BlueprintPart`, `DataManager::sourceOf()`), as is `Integration\SourceRenderer`: gcego/data is the domain-agnostic data engine other packages build on.

### Added

- Data mutations: `Data::create()`, `update()`, `delete()`, `forceDelete()`, `restore()`, `createMany()`, `updateMany()`, `deleteMany()` on sources that declare `Mutations\Writable` (field allowlist, Laravel validation rules, permitted operations). Writes lock the record inside the source's scope, authorize through policies (`create`, `update`, `delete`, `restore`, `forceDelete`), run `Mutations\BeforeSave` callbacks, roll back writes that would leave the scope, and dispatch `RecordCreated`, `RecordUpdated`, `RecordDeleted` and `RecordRestored` after commit. New `Contracts\SupportsMutations` (Eloquent and table sources), `Results\MutationResult`, `Exceptions\MutationException`.
- Transaction manager: `Data::transaction()` with rollback, deadlock and serialization-failure retries with exponential backoff and jitter, configurable retryable exceptions, isolation levels and savepoint nesting (`Transactions\TransactionManager`, `TransactionOptions`, `IsolationLevel`).
- Query caching: `Caching\Cached` caches query, record and aggregate results of named sources with `User`, `Tenant` or `Shared` isolation, version-token invalidation after gcego/data writes, Eloquent model events and `Data::invalidate()`, and stampede locks. Authorization, field rules and transforms still run for every caller.
- Tenant scoping: `Contracts\TenantResolver` (bound to `NullTenantResolver`), `Contracts\BelongsToTenant` models and `Tenancy\TenantScoped`. Reads are limited to the current tenant, creates stamp it, input can never set it, and calls without a tenant fail closed (`MissingTenantException`, 403). `QueryContext::forTenant()` and `acrossTenants()` for trusted code.
- Field-level security: `Security\FieldAccess` read and write rules (false, Gate abilities or closures). Unreadable fields are removed from records and cannot be filtered, sorted, searched or aggregated; unwritable fields are rejected with 403.
- Filtering: text operators (`contains`, `not_contains`, `starts_with`, `ends_with`), relation existence operators (`has`, `doesnt_have`), `Filterable::text()`, `Filterable::enum()`, `Filterable::relation()`, custom operators (`Contracts\CustomOperator`) and custom filters (`Filters\CustomFilter`, `Contracts\FilterHandler`).
- Relationship aggregates: `Relationships\RelationAggregate` counts, sums, averages, minimums, maximums and existence as subqueries, optional per request (`include=alias`) and sortable by alias.
- Performance: `QueryContext::profiled()` / `performance.profile` record statements (never bindings) with N+1 detection (`Performance\Profile`), slow-call logging and `SlowQueryDetected`, lazy-loading prevention on returned models, statement timeouts (MySQL/MariaDB, PostgreSQL; `QueryTimeoutException`, 503), a maximum pagination offset and a collection item limit (`Performance\Limits`).
- Transformation: `Transform::except()`, `Transform::map()` (fixed output shape) and `Transform::resource()` (Laravel API resources).
- `SourceQueried` read events for auditing (`events.reads`), `DataResult::meta()`, `$cache` and `$profile`, and new diagnostics for tenancy, writes and caching.
- `Data` facade (`Gcego\Data\Facades\Data`, auto-aliased) for `DataManager`.
- Scoped queries: `DataManager::scopedQuery(Source, $input, $context)` returns the rows a request may see as an unordered builder (view authorization, mandatory constraints and scopes such as tenancy, fixed and request filters, search), for trusted packages that aggregate on the database themselves (gcego/analytics). New `Contracts\ProvidesScopedQueries`, implemented by the Eloquent and query-builder providers.
- Aggregation: `DataManager::aggregate(Source, Aggregation, $input, $context)` computes count, sum, avg, min and max, optionally grouped by a column or a time bucket (day, week, month, year) over a date range, in the database for Eloquent and query-builder sources (in memory for collections). Same authorization, mandatory constraints, scopes and allowlisted filters as `query()`. New `Aggregation\{Aggregation, AggregateFunction, TimeBucket, AggregateResult}` and `Contracts\SupportsAggregation`.

### Changed

- The config file is only offered for publishing in the console. New configuration sections: `cache`, `transactions`, `performance`, `events`.
- `Contracts\ResolvesRecords::find()` takes a fifth `$aggregates` parameter; custom providers implementing it must add it.
- `Filters\Filter::$operator` and `Filterable::$operators` may hold `CustomOperator` instances; use `Filter::operatorName()` for the request name.
- Requires `illuminate/validation` ^13.0 and `psr/log` ^3.0.

## 0.1.0 - 2026-10-17

First release.

- `Source` component (the blueprint `source` part): model shorthand, Property syntax and configuration nodes.
- Providers: Eloquent, query builder (table or trusted factory) and in-memory collection, with capability interfaces.
- Fixed `Filter` conditions and allowlisted `Filterable` request filters with 11 operators and typed values.
- `Search` with an escaped, bound LIKE strategy (ILIKE on PostgreSQL) and pluggable `SearchStrategy`.
- `Sort` defaults, allowlisted `Sortable` keys and stable key tie-breaking.
- `Paginate`: page numbers, simple and cursor pagination, capped page sizes.
- `With` and allowlisted `Includable` eager loading, relation paths in filters and search.
- Scoped `RecordResolver` for integer, UUID, ULID and custom keys.
- Fail-closed `GateDataAuthorizer` (model policies), application `ScopeRegistry` and per-call `QueryContext` scopes.
- `QueryInspector` diagnostics and SQL explanation without binding values outside debug mode.
