# Column preferences

Users can do the following from the Columns menu (`Table::ColumnManager`,
on by default):
- show and hide columns (`Hideable` false keeps a column);
- reorder columns with up and down buttons (an accessible alternative to
  drag and drop);
- choose comfortable or compact density;
- reset everything.

The page size is remembered too.

## Storage

| Topic | Details |
| --- | --- |
| Where | `Contracts\PreferenceStore`; the default is `DatabasePreferenceStore` on `gcego_table_preferences` (publish the migration). |
| Scope | One row per table identity (`blueprint:subjects#table`), guard, user key (a string) and tenant. |
| Format | A versioned JSON state of at most 32 KB. |
| Turning it off | `gcego-tables.preferences.enabled = false`. |

## Restoring

Stored preferences are untrusted:

| Rule | Details |
| --- | --- |
| Lenient normalization | They go through the lenient normalizer with the user's current column access. |
| Permissions win | A column the user may no longer read never comes back, unknown columns are dropped, and unhideable columns cannot be hidden. |
| New columns | A column added to the definition after a layout was saved starts with its declared default (`Visible` false stays hidden). |
| Bad data | A broken or old-format row is ignored. |
| Failures | Storage failures are reported and never break the table. |

## Deleting a user

```php
app(PreferenceStore::class)->forgetOwner('web', (string) $user->getKey());
app(SavedViewRepository::class)->forgetOwner('web', (string) $user->getKey());
```

There are no foreign keys to a users table: the package cannot know the
application's user table or key type.
