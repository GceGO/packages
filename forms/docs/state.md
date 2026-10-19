# State, hydration and dehydration

## Hydration (server → browser)

`FormManager::hydrate($form, $record = null)`:

- **Readable fields:** each gets its record attribute, or its `Default` for a
  new record. BelongsToMany fields get the related ids.
- **Field formats:** dates become `Y-m-d`, date-times become the display zone
  (`Y-m-d\TH:i`), times become `H:i`.
- **Repeaters:** item lists become `{ "i<10 hex>": item }` maps with stable
  keys, so Livewire keeps DOM identity when items move.
- **Never hydrated:** `Hidden` fields and fields the user may not read are
  left out. Passwords are always `null`.

## Normalization (browser → server)

`FormManager::normalize()` is applied to every submission:

| Step | What happens |
| --- | --- |
| Reject unknown keys | Every key must be a declared field (or `<password>_confirmation`). Repeater keys must match `i<10 hex>`, and item keys must be the repeater's child fields. Anything else throws `InvalidStateException`, which surfaces as a 403. |
| Ignore `Hidden` keys | Their submitted values are dropped. |
| Normalize values | Text is trimmed and `''` becomes `null`. Numbers are cast only when valid, so `'abc'` fails validation instead of becoming `0`. Checkboxes become booleans. Multiple choices are de-duplicated. |

## Dehydration (validated → attributes)

`FormManager::process($form, $submitted, $record = null, $model = null)`
returns the validated values. It throws `ValidationException` when
validation fails.

**Included:**
- Visible, writable, submitted fields only.
- `Hidden` fields, with the server value (record or default).
- Repeaters, as ordered lists; fields hidden inside an item are dropped.
- Uploads, as private files saved as `<directory>/<random>.<ext>` on an
  allowed disk; the path is returned.
- Rich text, sanitized.
- Date-times, converted from the display zone to `app.timezone`.

**Skipped:**
- Empty passwords.
- Keys absent from the submission. They never overwrite stored values, so a
  partial update cannot wipe other fields.

## Persistence

Forms do not save anything. gcego/actions handlers do:

| Handler | What it does |
| --- | --- |
| `CreateRecord` and `UpdateRecord` | `fill()` (which honours `$fillable`/`$guarded`), set the tenant column on create, `save()` |
| `Persistence\SaveRecord` | the same, plus `sync()` for BelongsToMany fields, in the action's transaction |
