# Relationships (Eloquent sources)

```php
new With('topics', 'topics.lessons');                                  // always eager loaded
new With(['topics' => fn ($query) => $query->where('is_published', true)]);   // trusted constraint
new Includable('topics', 'topics.lessons');                            // ?include=topics.lessons
```

- **Eager loading:** one query per relation level, never one per record. A
  page of subjects with `topics.lessons` uses 4 queries whatever the page size
  (count, page, topics, lessons). Tests assert this.
- **Allowlist:** requests may only include exact allowlisted paths. Other paths
  return 400 (strict) or are ignored.
- **Validation:** relation names are checked against the model when the source
  is first used. Each segment must be a public, parameterless method returning
  a `Relation`. Building a relation doesn't query the database.
- **Scopes:** related models' global scopes apply. Use a `With` constraint to
  narrow further.
- **Record lookups:** `DataManager::find()` loads `With` relations, and accepts
  `include` too.
- **Paths in filters and search:** `new Filterable('topic', column: 'topics.name')`
  and `new Search('topics.name')` use `whereHas`.
- **Other providers:** query-builder and collection sources reject `With` and
  `Includable`.

Nothing is loaded automatically: relations not declared in `With` or
requested from `Includable` are never loaded by gcego/data.

## Relationship aggregates

Computed by the database as subqueries of the listing, never N+1:

```php
RelationAggregate::count('topics');                                         // topics_count
RelationAggregate::count('topics', 'published_topics', fn ($q) => $q->where('is_published', true));
RelationAggregate::sum('enrolments', 'fee');                                // enrolments_sum_fee
RelationAggregate::avg / min / max('enrolments', 'fee');
RelationAggregate::exists('topics');                                        // topics_exists
RelationAggregate::count('lessons')->optional();                            // only with ?include=lessons_count
new Sortable(['popular' => 'topics_count']);                                 // sort by an aggregate
```

Aggregates are added to records found by key as well. They need an Eloquent
source; cursor-paginated sources cannot sort by them.

## Relation conditions

`Filterable::relation('topics')` and `new Filter('topics', operator: FilterOperator::Has)`
test the existence of related records; `Filter`/`Filterable` columns such as
`topics.name` mean "has a related record matching" (see [filtering](filtering.md)).

