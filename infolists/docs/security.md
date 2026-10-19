# Security

| Threat | Defence |
| --- | --- |
| Forged definition, model or schema | Definitions come only from the registry. The host's definition and record are `#[Locked]`; entry paths and classes are never read from the request. |
| Record access | `SecurityManager::record()` performs a scoped lookup (tenant and data scopes; missing or out of scope is 404) and then checks the `view` ability or Source requirements (403). It is re-checked on every Livewire request. |
| Forged keys | Keys are bound parameters of the scoped lookup. Empty or oversized keys are refused. Cross-tenant keys are not found. |
| Entry exposure | `Access` read rules (gcego/security `FieldRule`) are decided per user and record. An unreadable entry is never resolved: its value is not read, rendered or serialized, and resolvers are not called. `mask: true` shows a mask without reading the value. Model `$hidden` attributes are never read. |
| Section exposure | Requirement nodes on layouts hide the whole layout without resolving its entries. |
| Visibility | `VisibleWhen` never grants access, and conditions on unreadable paths are false. |
| Relations | Paths are validated against the model, loaded once, and scoped by the related model's data scopes. They are never lazy-loaded. |
| JSON columns | `KeyValue` shows only declared keys and refuses secret-looking keys unless `AllowSensitive` is set. |
| XSS | Everything is escaped. Markdown and HTML pass through commonmark (raw HTML stripped) and symfony/html-sanitizer (allow-list). Badges map values to trusted variants. Icons come from a trusted set. Links and images accept only http(s), mailto and relative URLs. New tabs get `noopener noreferrer`. |
| Private media | `Image::Private` uses short-lived temporary URLs. When a disk cannot sign URLs, nothing is shown and the path is never exposed. |
| Serialization | The Livewire snapshot contains only the definition and the record key. |
