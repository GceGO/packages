# Relationships (Eloquent sources)

```php
Relations::make(['department', 'manager']);                                   // always eager loaded
Relations::make(['reports' => fn ($query) => $query->where('is_active', true)]);   // trusted constraint
Includables::make(['department', 'reports', 'reports.department']);           // ?include=reports.department
Aggregates::make([
    RelationAggregate::count('reports'),                                        // reports_count
    RelationAggregate::count('reports', 'active_reports', fn ($q) => $q->where('is_active', true)),
    RelationAggregate::sum('payments', 'amount'),                               // payments_sum_amount
    RelationAggregate::avg / min / max('payments', 'amount'),
    RelationAggregate::exists('reports'),                                       // reports_exists
    RelationAggregate::count('certificates')->optional(),                       // only with ?include=certificates_count
]);
Sortables::make(['popular' => 'reports_count']);                                // sort by an aggregate
```

- **Eager loading:** one query per relation level, never one per record. A
  page with `topics.lessons` uses 4 queries whatever the page size. Tests
  assert this.
- **Allowlist:** requests may only include exact declared paths (and
  optional aggregate aliases); others return 400 (strict) or are ignored.
- **Depth:** a path crosses at most three relations (`RelationPaths::MaxDepth`),
  in relations, includes, search and filter columns.
- **Validation:** relation names are checked against the model when the
  source is compiled: each segment must be a public, parameterless method
  returning a `Relation`. Building a relation does not query.
- **Scopes:** related models' global scopes apply; for tenant-owned related
  models (`BelongsToTenant`), every level of every path is also limited to
  the call's tenant (eager loads, includes, aggregates, relation filters and
  search).
- **Record lookups:** `find()` loads `Relations`, accepts `include`, and adds
  aggregates.
- **Field security:** `FieldAccess::hidden('manager.salary')` removes fields
  of loaded related records.
- **Other providers:** query-builder and collection sources reject relations,
  includes, aggregates and relation filters.

Nothing is loaded automatically: relations not declared in `Relations` or
requested from `Includables` are never loaded by gcego/data. Relations an
application lazy loads afterwards are outside its control; enable
`performance.prevent_lazy_loading` to catch them.

## Relation conditions

```php
Filterable::make('reports', [Has::make(), DoesntHave::make()]);   // ?filter[reports][has]=1
Filter::make('reports', operator: DoesntHave::make());            // mandatory
Filterable::make('department', [StringType::make(), Column::make('department.name')]);
```

A dotted column means "has a related record matching" (see [filtering](filtering.md)).
