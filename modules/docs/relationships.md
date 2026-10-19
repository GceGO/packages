# Relationships

```php
new Relation('topics', new Property(Relation::Resource, 'topics'))
new Relation('tags', 'Tags', new Property(Relation::Resource, 'tags'), new Attach, new Detach)
new Relation('notes', new Property(Relation::Model, Note::class), new Table(...), new Form(...), new Create)
```

- **Name:** the relationship method on the parent model (camelCase allowed; URLs use kebab-case).
- **Definitions:** `Resource` names a resource of the same module whose model, Table, Form, Infolist, labels and actions are reused unless the relation declares its own. Without `Resource`, `Model` is required.
- **Supported relationships:** any Eloquent relationship that lists records: HasMany, MorphMany, BelongsToMany, MorphToMany, HasManyThrough. Create works for HasOne/HasMany/Morph(One/Many) (sets the foreign key) and BelongsToMany (saves, then attaches). Attach and Detach work for BelongsToMany only.

## The parent

Relation pages have a `{parent}` route parameter, constrained to the parent resource's record key shape. `ParentRecords` finds it:

- **page requests:** from the current route, when it is one of this relation's pages;
- **Livewire updates** (the page's table, form or action hosts): from the page URL in the component snapshot, which Livewire checksums, so it cannot be changed without the application key;
- **anything else:** none.

A key proves nothing: the parent is looked up through the parent resource's Source (tenant and security scopes; 404 out of scope) and authorized through gcego/security (`view` to read, `update` to create, attach or detach; 403 when denied).

## Enforcement

| Mechanism | Where | What it does |
| --- | --- | --- |
| `ParentScope` | the relation's Source (`Query` constraint) | Every list, lookup and action sees only `$parent->relationship` records. No parent means no rows. |
| `ParentAccess` | requirement on every relation page and relationship action | Denies when the parent is missing or forbidden. Outside the relation's pages (a link drawn on the parent's view page) there is no parent to check, so it allows; the constraint still applies. |
| `RelatedRecords` | handlers | Create links through the relationship. Attach re-resolves the chosen key through the related resource's Source and authorizes `view`. Detach detaches only the action's (already constrained) record. |

## Limits

- Relationship pages are separate pages linked from the parent's view page. They are not embedded in it: a gcego/pages page has one Source.
- Attach takes the related record's key in a plain input. A searchable picker needs an action-owned form, which gcego/forms does not offer yet.
- Relations of relations (nested managers) are not generated.
