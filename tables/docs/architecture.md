# Architecture

```
Blueprint (registry)
├── Source                     gcego/data: model, mandatory scopes, policies
└── Table                      columns, filters, actions, Search/Pagination/Sort/Tailwind
        │ SchemaCompiler (pure, cached per definition)
        ▼
    TableSchema
        │
TableHost (Livewire) ──► TableManager
                          ├── resolve()      registered blueprint only (never a class from the client)
                          ├── authorize()    gcego/security requirements, every request
                          ├── access()       column read decisions (FieldRules) for this user
                          ├── normalizer()   untrusted state → TableState
                          └── query()        EffectiveSources + QueryInput + QueryContext → DataManager
        ▼
TableRenderFrame ──► Runtime ──► TableRenderer (table.blade.php)
                                  ├── ColumnRenderer  header cells and sort controls
                                  ├── FilterRenderer  filter controls
                                  └── cell views      columns/*.blade.php
```

## Who owns what

| Concern | Owner |
| --- | --- |
| Query execution, filter operators, search SQL, pagination | gcego/data |
| Tenancy, policies, field rules, record lookup | gcego/security |
| Executing actions, confirmations, bulk policies | gcego/actions |
| Edit and Create input forms | gcego/forms (through gcego/actions) |
| Class lists and themes | gcego/chroma |
| Definitions, state, presentation, preferences, views, export iteration | gcego/tables |

## Effective sources

The Source stays the authority. For each user's set of readable columns,
`EffectiveSources` adds:

- a `Search` over searchable readable columns;
- `Sortable` keys for sortable readable columns;
- one `Filterable` per filter;
- `With` for every relation a visible column reads;
- `Paginate` from the table's `Pagination`;
- the table's default `Sort` nodes.

Anything the Source declares itself wins. Results are cached per Source
and access set.

## State

`TableState` is a small typed value:
- search, filters, sorts and page;
- page size, cursor, column order, hidden columns and density.

`TableStateNormalizer` builds it from untrusted input. It runs strict for
requests (unknown names are refused) and lenient for stored state, where
anything that no longer applies is dropped and reported.

## Lifetimes

| Lifetime | Services |
| --- | --- |
| Singletons | `TablesConfig`, `TableManager` (schema cache), `EffectiveSources`, `Icons` (filled at boot) |
| Scoped (per request) | the render context, actions, rows, preferences, saved views, inspector, CSV writer, relationship options |

User-dependent services are always resolved per call, so the package is
safe under Octane.
