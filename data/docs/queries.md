# Queries and the pipeline

Definitions never execute themselves; `DataManager` (the `Data` facade) does.

## Executing

```php
$page = Data::query($source);                                   // defaults, current user
$page = Data::query($source, $request);                         // the request's query string
$page = Data::query(EmployeesSource::class, $request);          // a SourceProvider
$page = Data::query($source, ['filter' => ['age' => ['gte' => '18']], 'sort' => '-created_at']);
$page = Data::query($source, QueryInput::fromRequest($request, 'employees'));   // ?employees[filter][...]
$page = Data::query($source, context: QueryContext::for($user)->constrain(new DepartmentScope($id)));
$page = Data::query($source, context: QueryContext::system());   // jobs/console: no authorization

$employee = Data::findOrFail($source, $id);                     // scoped, authorized record
$total = Data::aggregate($source, new Aggregation(AggregateFunction::Sum, 'salary'));
```

## Stages

1. **Declaration:** `Source::define(...)` (no I/O).
2. **Validation:** definitions validate themselves; `SourceCompiler`
   validates references.
3. **Compilation:** one `SourceDefinition`, cached per `Source` instance; the
   provider prepares it (relation paths, capabilities).
4. **Authorization:** `DataAuthorizer::authorize(ViewAny)`, before any query.
5. **Input:** `QueryInput` becomes a `QueryPlan` through the allowlists; fields
   the caller may not read are removed from it.
6. **Scope enforcement**, in order: the tenant scope, `Constraints` queries,
   registered scopes (`Data::scope()`), `QueryContext` constraints, then
   `Constraints` filters.
7. **Execution:** request filters (one nested AND group), search (one group
   per term), ordering with the unique key, eager loading and aggregates,
   pagination, under the statement timeout. Or the result cache answers.
8. **Transformation:** per record, unreadable fields are removed, lazy loading
   is guarded, then `Transform` runs; a `DataResult` is returned.

## Trusted constraints

```php
Constraints::make([
    Query::make(fn (Builder $query) => $query->where('is_active', true)),
    Query::make(new ActiveOnly),                 // a QueryConstraint
]);
```

- **Arguments:** the builder (or, for collections, the collection) and the
  `SourceDefinition`.
- **Return value:** builder constraints must change and return *the builder
  they receive*; a different builder is rejected, because it would discard
  earlier scopes.
- **Isolation:** each constraint's where clauses are grouped, so an `orWhere`
  inside one can never widen another.

## Results

`DataResult`: `items()`, `collection()`, `paginator()`, `pagination`
(`PaginationResult`), `applied`, `durationMs`, `cache`, `profile`, `meta()`;
`Arrayable` and `JsonSerializable` as `{data, pagination}`.

## Transformations

```php
Transform::only(['id', 'name', 'email']);
Transform::except(['internal_notes']);
Transform::map(['id', 'full_name' => 'name', 'reports' => fn (Employee $e) => $e->reports_count]);
Transform::resource(EmployeeResource::class);
Transform::make(fn (Employee $e) => [...]);      // or a RecordTransformer
```

Transforms run after authorization, tenant scoping and field security, for
every caller (cached or not), so they cannot expose what the caller may not
see: `only()`, `except()` and `map()` read models through `toArray()`, after
unreadable fields were removed. A closure that lazy loads a relation leaves
gcego/data's control; eager load it instead.
