# CRUD actions

The blueprint must contain a gcego/data `Source` with an Eloquent model.

| Action | Ability | Record | Confirmation | Behavior |
| --- | --- | --- | --- | --- |
| `Create` | `create` (model class) | none | no | `new Model`, `fill(validated)`, sets the tenant column from the verified tenant, `save()` |
| `Edit` | `update` (record) | scoped | no | `fill(validated)->save()` |
| `View` | `view` (record) | scoped | no | read-only; result `data.intent = view`, no transaction |
| `Delete` | `delete` (record) | scoped | **yes** | `delete()`: a soft delete for SoftDeletes models |
| `Restore` | `restore` (record) | scoped, **trashed only** | configurable (`confirmation.restore`) | `restore()` |
| `ForceDelete` | `forceDelete` (record) | scoped, **trashed only** | **yes** | `forceDelete()` |

## Validated input

Create and Edit persist **only** validated input from an `InputResolver`:

```php
new Create(new Input([
    'name' => ['required', 'string', 'max:120'],
    'is_published' => ['boolean'],
]), new FieldRule('is_published', write: new Permission('subjects.publish')))
```

- **Writable fields:** keys without a rule are refused (403), including tenant
  columns, `handler` and anything else a crafted request adds.
- **Field permissions:** a `FieldRule` write requirement makes a field writable
  only for users who hold it.
- **Validation errors:** these return `ValidationFailed` with errors per field.
  Nothing is saved.
- **Model protection:** `fill()` applies `$fillable`/`$guarded`, casts,
  mutators and model events.
- **No resolver:** without any `InputResolver` for the action, it fails with
  `invalid_action` and saves nothing. The inspector reports this as an error.

## Records

Records are resolved through the blueprint's `Source`, so global scopes, the
source's constraints and the gcego/security tenant and role scopes apply.

- **Out of reach:** other tenants', missing and soft-deleted records answer
  `NotFound`.
- **Restore and ForceDelete:** these look only among trashed rows. Only the
  soft-delete scope is lifted; every other scope stays.
- **Keys:** integer, UUID, ULID and custom string keys all work.
