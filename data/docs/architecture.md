# Architecture

```
 declaration (trusted, immutable, no I/O)     execution (per call, DataManager / Data facade)
 ─────────────────────────────────────────    ─────────────────────────────────────────────────
 Source::define(                              Data::query($source, $request, $context)
     Name::make(...), Model::make(...),           │
     Constraints::make([...]),                    ├─ SourceCompiler ─► SourceDefinition (cached per Source)
     Filterables::make([...]),                    ├─ ProviderRegistry ─► provider->prepare()        (no DB)
     Search::make([...]), Sort::make(...),        ├─ DataAuthorizer::authorize(ViewAny)             (no DB)
     Pagination::make(25), ...)                   ├─ QueryResolver: QueryInput ─► QueryPlan         (allowlists)
        │                                         ├─ FieldGuard: unreadable fields leave the plan
        │  or a SourceProvider class              ├─ constraints = tenant + Constraints + scopes + context
        ▼                                         ├─ provider->fetch() or QueryCache               (timeouts, limits)
 validated when defined and compiled              └─ redact fields ─► Transform ─► DataResult
```

## Definitions versus execution

- **`Source::define()`:** the declaration API, typed definition objects in
  `Gcego\Data\Definitions` (one per class), mirroring `Blueprint::define()`.
  Defining, compiling, normalizing or resolving a source never queries;
  tests assert zero queries, and that no connection is opened at boot.
- **`SourceDefinition`** (`Gcego\Data\Compilation`): `SourceCompiler`
  validates references and merges the definitions into this typed form.
- **Execution:** only through `DataManager` (`QueryPipeline`,
  `RecordResolver`, `MutationPipeline`), returning `DataResult`,
  `RecordResult`, `AggregateResult` or `MutationResult`.
- **Dependencies:** gcego/core and Illuminate packages only. gcego/data never
  imports registry, blueprints, components, runtime or UI packages; they
  integrate by implementing `SourceProvider` or calling `Data`.

## Writes

`MutationPipeline` runs every write in a transaction: lock the record inside
the source's scope, authorize, allowlist and validate input, run `BeforeSave`,
stamp the tenant, write, re-check the scope, then invalidate the cache and
dispatch events after commit. See [mutations](mutations.md) and
[transactions](transactions.md).

## Authorization boundary

`DataAuthorizer` runs before any query or write. Declaring a Source
grants nothing. The default `GateDataAuthorizer` uses model policies and fails
closed. `gcego/security` can bind its own implementation; see
[security](security.md).

## Integration with future packages

- **gcego/tables:** reads `SourceDefinition::$filterables`, `$sortables` and
  `$search` to build filter, sort and search UI. It keeps `QueryInput` as its
  Livewire state (`QueryInput::toArray()` / `fromArray()`), calls
  `DataManager::query()`, and renders `DataResult::items()` with
  `$pagination`.
- **gcego/forms and gcego/actions:** resolve the record being edited or acted
  on with `DataManager::find()`/`findOrFail()`, and save through
  `Data::create()`/`update()`/`delete()`, so validation, authorization,
  tenancy and cache invalidation are never re-implemented. gcego/data never
  mass-assigns: only `Writable` fields are written.
- **gcego/security:** binds `DataAuthorizer`, and tenancy packages bind
  `TenantResolver`; further constraints go through `DataManager::scope()` or
  `QueryContext::constrain()`.
- **Package-specific options:** packages implement `SourceConfiguration`;
  unknown nodes are kept in `SourceDefinition::$extensions`.
