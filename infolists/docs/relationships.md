# Relationships

```php
new Text('category.name', 'Category')                                     // BelongsTo
new ListEntry('tags.label', 'Tags')                                       // BelongsToMany / HasMany values
new RepeatableEntry('topics', new Text('title'), new Text('teacher.name')) // HasMany with a nested BelongsTo
```

## How relations are loaded

| Rule | Details |
| --- | --- |
| Paths are definition-time | Relation paths come from entry names (and `AltColumn`, `UrlColumn`, `RouteParameters`). |
| Validated | gcego/data's `RelationPathValidator` checks each path against the model. Paths that are not relations (array or JSON attributes such as `settings.term`) are read from the attribute instead. |
| Loaded once | Every valid relation is eager-loaded with the record, in one query per relation, never per entry or per item. A test asserts this. |
| Tenant-scoped | Each eager load applies the related model's registered gcego/data scopes (tenancy), so a related record of another tenant is never loaded, even with inconsistent foreign keys. |
| Never lazy | Cells never lazy-load: an unloaded relation reads as empty. |
| Empty values | Empty relations show the placeholder; `HiddenWhenEmpty` hides the entry instead. |
