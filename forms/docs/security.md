# Security

| Threat | Defence |
| --- | --- |
| Forged schema, rules or options | The definition is resolved from the registry by a locked identity. Rules, options, handlers and editors are PHP. The browser sends values only. |
| Mass assignment | Unknown keys are refused: `InvalidStateException` (400) on Livewire updates, a 403 `Denied` result through actions. Only declared, visible, writable, submitted fields are returned, and handlers use `fill()`. |
| Hidden fields | `Hidden` values never reach the browser and are taken from the server. Submitted values are ignored. |
| Field authorization | `new Access(read: …, write: …, mask: true)` becomes a gcego/security `FieldRule`. Unreadable fields are not hydrated or rendered. Unwritable fields are rendered disabled (or masked) and never written. |
| Passwords | Never hydrated, rendered or prefilled; `autocomplete="new-password"`; cleared from state after a submit; never in error messages. |
| Sensitive attributes | Only fields declared in the form are hydrated, so attributes outside it never reach the client. |
| Relationship values | Scoped and authorized options through gcego/data. Submitted ids are re-checked with the same scope. |
| Tenancy | Records are resolved through gcego/security's `RecordAuthorizer`. Another tenant's record and a missing record get the same response. |
| XSS | Every value and label is escaped with `{{ }}`. Rich text is sanitized with `symfony/html-sanitizer` against an element allow-list. Links are limited to http, https and mailto, and forced to `rel="noopener noreferrer"`. |
| Uploads | `mimes` sniffs content; `.php` uploads are blocked; size is capped by config; files are stored privately on allowed disks under random names. Path strings from the browser are accepted only if they equal the record's current value. |
| Writes during render | Rendering never writes. Persistence happens only in action handlers, after authorization, inside a transaction. |
| Queries during definition | Constructing and compiling a form never queries. |

## Field access example

```php
new Textarea('secret_notes', 'Secret notes', new Access(
    read: new Permission('subjects.secrets'),
    write: new Permission('subjects.secrets'),
))
```
