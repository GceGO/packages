# Security

A table is never a generic database browser: only registered blueprints,
their Source and their declared columns are exposed.

| Threat | Defence |
| --- | --- |
| Forged definition or model | The host resolves `definition` through the registry and is `#[Locked]`. Classes, models and paths from the client are refused. |
| Unauthorized access | gcego/security requirements of the blueprint and table are checked on mount and on every request. Queries run through gcego/data with the user's policies and tenant scope. |
| Forged columns, sorts, filters | Only declared, readable columns and filters are accepted. Unknown names are a 400 (or a reset with a notice for URL or stored state). Values are validated per filter type and options. SQL only ever sees bindings and allowlisted columns. |
| Column exposure | `Access` read rules (gcego/security `FieldRule`) are decided per request. Unreadable columns are removed from cells, headers, search, sort, filters, preferences, saved views and exports. `mask: true` shows a mask without sending values. Model `$hidden` attributes are never read. |
| Record exposure | Rows come from the scoped query. Row keys and selections are untrusted. gcego/actions re-resolves each key through the scoped source and re-authorizes it; bulk actions are all or nothing by default. |
| Forged actions | Only the table's row and bulk actions can be requested. Execution re-checks everything, and hidden actions stay protected. |
| Cross-tenant data | Tenant scopes apply to queries, relationship filter options, preferences and saved views (keyed by tenant). |
| XSS | Every value is escaped. Formatters return text. Icons come from a trusted set. Image and link URLs must be http(s), mailto or root-relative (no `javascript:` or `data:`). New-tab links get `rel="noopener noreferrer"`. |
| CSV injection | `CsvWriter` prefixes text starting with `=`, `+`, `-`, `@`, tab or CR. |
| Payload abuse | Search length, page size, selection count, view count and stored state size are all bounded. |
| Error leakage | Query errors show a generic message; details go to the exception handler only. |
