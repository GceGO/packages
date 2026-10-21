# Architecture

```
 definition (trusted, immutable)             execution (per call)
 ───────────────────────────────             ─────────────────────────────────────────────
 new Source(Subject::class,                 DataManager::query($source, $request, $context)
     new Filter(...), new Filterable(...),        │
     new Search(...), new Sort(...), ...)         ├─ SourceCompiler ─► SourceDefinition (cached per Source)
        │                                         ├─ ProviderRegistry ─► provider->prepare()   (no DB)
        │  part of a Blueprint                    ├─ DataAuthorizer::authorize(ViewAny)        (no DB)
        ▼                                         ├─ QueryResolver: QueryInput ─► QueryPlan    (allowlists)
 gcego/registry stores scalars only               ├─ constraints = Query nodes + ScopeRegistry + QueryContext
 (callbacks never reach manifests)                ├─ provider->fetch(definition, plan, constraints)
                                                  └─ Transform nodes ─► DataResult
```

## Definitions versus execution

- **`Source`:** a `gcego/morfaw` component, and the `source` part of a
  blueprint. It is immutable and describes data without reading it.
  - Constructing, validating, normalizing, registering or rendering a Source
    never queries.
  - Tests assert zero queries for each of these, and that no database
    connection is opened at boot.
- **`SourceDefinition`:** `SourceCompiler` merges properties and configuration
  nodes into this typed form.
- **Execution:** only happens through `DataManager` (or `QueryPipeline` and
  `RecordResolver`), and returns `DataResult` or `RecordResult`.

## Trusted versus untrusted

| Trusted (developer code) | Untrusted (request) |
| --- | --- |
| `Source` and its nodes, column names, relation paths | `QueryInput`: filter, search, sort, page, per_page, cursor, include |
| `Query` callbacks, `With` constraints, `Transform`, scopes | only *names* allowlisted keys and supplies values |
| `QueryContext` (user, request-scoped constraints) | |

`QueryResolver` turns input into a `QueryPlan` that contains only trusted
columns, cast values and capped sizes. Providers never see raw input.

## Provider capabilities

`DataProvider` (`prepare`, `fetch`) is the only required contract. The
optional capabilities are separate interfaces:

- `ResolvesRecords`
- `SupportsRelationships`
- `SupportsCursorPagination`
- `InspectsQueries`

The pipeline checks them before running, so unsupported features fail early
with clear errors.

| | eloquent | query | collection |
| --- | --- | --- | --- |
| fetch, records | yes | yes | yes |
| relationships | yes | no | no |
| cursor pagination | yes | yes | no |
| explain | yes | yes | no |

## Authorization boundary

`DataAuthorizer` runs before any query. Registering a Source or a Blueprint
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
  on with `DataManager::find()`/`findOrFail()`, so a known key never bypasses
  the source's scope. Writes stay in those packages: gcego/data never
  mass-assigns or mutates.
- **gcego/security:** binds `DataAuthorizer` and registers tenant constraints
  with `DataManager::scope()` or `QueryContext::constrain()`.
- **Package-specific options:** packages implement `SourceConfiguration`;
  unknown nodes are kept in `SourceDefinition::$extensions`.
