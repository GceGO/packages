# Generated pages

`ResourcePageFactory` builds gcego/pages pages from the compiled definitions:

| Operation | Class | Children |
| --- | --- | --- |
| index | `IndexPage` | Source, Table, the "New" link (when there is a create page), a View row action (when there is a view page), the declared actions |
| create | `CreatePage` | Source, Form, the Create action (yours or `FormFooter`) |
| view | `ViewPage` | Source, Infolist, the "Edit" link, relationship links (`:record` parameter), the declared actions |
| edit | `EditPage` | Source, Form, the Edit action, the declared actions |

The actions declared on the resource (except Create and Edit) are placed on the list and record pages. Their own placements decide where they appear: rows, record, header, bulk.

## Names, routes, paths

| | List | Create | View | Edit |
| --- | --- | --- | --- | --- |
| Page name | `m.r` | `m.r.create` | `m.r.view` | `m.r.edit` |
| Route (below `gcego.`) | `m.r.index` | `m.r.create` | `m.r.view` | `m.r.edit` |
| Path (below `/admin`) | `[slug/]r` | `…/create` | `…/{record}` | `…/{record}/edit` |

Here `m` is the module and `r` the resource. When `r` equals `m`, the page name is just `m`. Registry identities replace dots with `-` (`page:m-r-edit`).

## Overrides and custom pages

A page declared inside a resource is named after it:

- **Named after an operation** (`index`, `create`, `view`, `edit`), it replaces that generated page. It keeps the generated name, path and route.
- **Any other name** adds a page: `m.r.<name>`, at `…/<name>` (or `…/{record}/<name>` for record pages).

Either way it gets the module's and resource's requirements, and the resource's Source if it has none. It is then validated as a page.

Pages declared in the module are named `m.<name>`, prefixed by the module's Slug when set, and get the module's requirements.
