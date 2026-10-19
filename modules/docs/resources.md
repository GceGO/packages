# Resources

```php
new Resource(
    new Property(Resource::Name, 'subjects'),          // default: the model's plural, kebab-case
    new Property(Resource::Model, Subject::class),     // or new Resource(Subject::class), or a Source
    new Property(Resource::Label, 'Subject'),          // default: from the name
    new Property(Resource::PluralLabel, 'Subjects'),
    new Property(Resource::Slug, 'subjects'),          // URL segment (default: the name)
    new Property(Resource::Icon, 'star'),
    new Property(Resource::RecordTitle, 'name'),
    new Property(Resource::RecordKey, 'id'),           // id, uuid, ulid, slug, key
    new Property(Resource::Operations, ['index', 'view']),
    new Property(Resource::Permissions, ['index' => 'subjects.view', 'create' => 'subjects.create']),
    new Property(Resource::Navigation, true),
    new Property(Resource::NavigationLabel, 'All subjects'),
    new Property(Resource::NavigationOrder, 1),
    new Permission('subjects.access'),                 // required by every page of the resource

    new Source(Subject::class, new Filter('status', 'active')),   // gcego/data: scopes and constraints apply everywhere
    new Table(...), new Form(...), new Infolist(...),
    new Create, new Edit, new Delete, new Restore, new ForceDelete, new BulkDelete, new Action(...),
    new Relation(...),
    new Page(...),                                     // custom pages (or overrides named index/create/view/edit)
)
```

## Operations

`Resource::operations()` decides which pages exist:

| Operation | Condition |
| --- | --- |
| `index` | a `Table` |
| `view` | an `Infolist` |
| `create` | a `Form`, plus the `Create` action, or `create` in `Operations` or `gcego-modules.resources.default_operations` |
| `edit` | a `Form`, plus the `Edit` action, or `edit` in `Operations` or `default_operations` |

`Operations` also restricts: listed operations only.

## Data

The records are the resource's `Source` (or `new Source(Model::class)`). gcego/data applies it everywhere: lists, record lookups, actions, form options, its global scopes, tenant scope and security scopes. A resource with a Table, Form or Infolist needs a Source or a Model.

## Labels and localization

Labels are plain text or translation keys. Generated titles come from `gcego-modules::messages` (English and French): "Create :label", "Edit :record", "New :label".
