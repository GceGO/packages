# Selection

Row checkboxes appear when the table has bulk actions, or with
`new Property(Table::Selectable, true)`.

| Topic | Details |
| --- | --- |
| Keys | Selected rows are keys (strings, so integer, UUID and ULID keys all work), stored in the Livewire property `$selected`. |
| Untrusted input | Keys are de-duplicated, must match `[A-Za-z0-9_\-:.]`, are at most 191 characters, and are capped at `gcego-tables.selection.max_records` (500). |
| Select page | The header checkbox adds the visible page's keys; select-all-matching is deliberately not offered, to keep selections bounded. |
| Clearing | Clear selection empties it. Searching or filtering also clears it, as does a successful action. |
| What selection grants | Nothing. Bulk actions run through gcego/actions, which re-resolves every key through the scoped source and re-authorizes each record. Its default all-or-nothing policy refuses the batch if any key is out of scope, unknown or denied. |
