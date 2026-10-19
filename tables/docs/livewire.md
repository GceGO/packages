# Livewire

```blade
<livewire:gcego-table definition="subjects" />
```

## Properties

| Property | Kind | Notes |
| --- | --- | --- |
| `definition` | `#[Locked]` | resolved through the registry on every request |
| `search`, `filters`, `sort`, `page`, `perPage`, `selected` | untrusted | normalized on every render; unknown filters refused on update |
| `cursor`, `order`, `hidden`, `density`, `view`, `notice` | `#[Locked]` | changed only by methods |

## Methods

| Group | Methods |
| --- | --- |
| Sorting and paging | `sortBy(column, add)`, `gotoPage`, `nextPage`, `previousPage`, `gotoCursor` |
| Search and filters | `resetSearch`, `removeFilter`, `resetFilters`, `resetTable` |
| Selection | `selectPage`, `clearSelection` |
| Actions | `runAction(action, key)`, `runBulkAction(action)` |
| Columns | `toggleColumn`, `moveColumn(column, ±1)`, `resetColumns`, `setDensity` |
| Saved views | `saveView`, `loadView`, `renameView`, `deleteView`, `setDefaultView` |
| Export | `export` (tables with `Table::Exportable`) |
| Other | `refresh` |

## Every request

1. Resolve the blueprint.
2. Check its gcego/security requirements (403 when denied).
3. Decide column access.
4. Normalize state.
5. Query through gcego/data.
6. Build view rows.
7. Render through gcego/runtime.

## States

| State | Behavior |
| --- | --- |
| Loading | `wire:loading` overlay with a spinner and status text. |
| Empty | "No records yet", or "No matching records" with a reset button when search or filters are active. |
| Error | A safe message with a retry button. The exception is reported, never shown. |
| Invalid state | State from the URL or an old session is reset with a notice. |

## URL state

`new Property(Table::UrlState, true)` keeps search, filters, sort and page
in the query string, prefixed by the table name (`subjects_search`,
`subjects_sort`, …). URL values are untrusted like any other state.

## Snapshot

Only small scalars: no models or rows. In the tests it is about 900 bytes.
