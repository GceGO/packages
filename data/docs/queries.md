# Queries and the pipeline

## Executing

```php
$data = app(DataManager::class);   // or the Data facade

$page = $data->query($source);                                   // defaults, current user
$page = $data->query($source, $request);                         // Illuminate\Http\Request query string
$page = $data->query($source, ['filter' => ['level' => '2'], 'sort' => '-name']);
$page = $data->query($source, QueryInput::fromRequest($request, 'subjects'));   // ?subjects[filter][...]
$page = $data->query($source, context: QueryContext::for($user)->constrain(new InstitutionDataScope($id)));
$page = $data->query($source, context: QueryContext::system());   // jobs/console: no authorization
```

## Order of operations

1. **Prepare:** compile the source and prepare the provider. Definition errors
   surface here, before any I/O.
2. **Authorize:** `DataAuthorizer::authorize(ViewAny)`.
3. **Validate input:** `QueryInput` becomes a `QueryPlan`; see
   [filtering](filtering.md), [sorting](sorting.md) and
   [pagination](pagination.md).
4. **Base query:** Eloquent builders include global scopes.
5. **Mandatory constraints**, in this order:
   1. the source's `Query` nodes;
   2. registered scopes (`DataManager::scope()`);
   3. `QueryContext` constraints;
   4. fixed `Filter` nodes.
6. **Request filters:** one nested AND group.
7. **Search:** one nested AND group per term.
8. **Ordering:** requested or default sorts, then the unique key.
9. **Eager loading:** `With` and requested `Includable` relations.
10. **Pagination:** this executes the query (count and select, select only, or
    cursor).
11. **Results:** `Transform` nodes run, and you get a `DataResult`.

## Trusted constraints

```php
new Query(fn (Builder $query) => $query->where('is_published', true)->with('topics'));
new Query(new PublishedOnly);              // a QueryConstraint class
```

- **Arguments:** callbacks receive the builder (or, for collection sources, the
  collection) and the `SourceDefinition`.
- **Return value:** builder constraints must change and return *the builder
  they receive*. Returning a different builder is rejected, because it would
  discard earlier scopes.
- **Isolation:** each constraint's where clauses are grouped, so an `orWhere`
  inside one constraint can never widen another:
  `(earlier scopes) AND (is_published = 1 OR ...)`.

## Results

`DataResult`:

- **`items()`:** models, arrays or items, after transforms.
- **`collection()`:** the same items as a collection.
- **`paginator()`:** the Laravel paginator, for `links()`.
- **`pagination`:** a `PaginationResult` (mode, perPage, count, hasMorePages,
  currentPage, total, lastPage, from, to, nextCursor, previousCursor).
- **`applied`:** the request keys that took effect.
- **`durationMs`:** how long the provider took.
- **Serialization:** it is `Arrayable` and `JsonSerializable` as
  `{data, pagination}`. Models serialize through their own `toArray()`.

## Transformations

```php
new Transform(fn (Subject $subject) => ['id' => $subject->id, 'label' => $subject->code.' '.$subject->name]);
new Transform(new SubjectSummary);         // RecordTransformer
Transform::only('id', 'name', 'code');     // reads toArray(): $hidden attributes never appear
```

Transforms are trusted server-side code, applied to listings and to resolved
records. Presentation formatting (table cells, form fields) belongs in the UI
packages.
