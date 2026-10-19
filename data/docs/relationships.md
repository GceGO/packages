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
