# Changelog

## Unreleased

- **Added:** Filament-style authoring on gcego/morfaw. `Table::make()`, `Text::make()` and every other column and filter have `make()`.
- **Added:** per-row closures. Presentation properties (`tooltip`, `placeholder`, `limit`, a badge's variants, a link's URL, ...) accept closures injected with `$state`, `$record` (also by model type) and `$column`. Each row gets a resolved copy of the column (`Column::forRow()`), and results are type-checked against the property and escaped as before.
- **Added:** `state(fn (Subject $record) => ...)` for computed columns, and `formatStateUsing(fn ($state, Subject $record) => ...)` with named and typed injection (`formatUsing()` keeps its positional `($value, $record)` signature). Exports use the same values.
- **Added:** `visible(fn (?User $user): bool => ...)` and `hidden(...)` decide once per request whether a column exists. A removed column is neither readable nor masked, so it is not queried, sorted, searched, shown or exported. `visible(false)` keeps its meaning: hidden until the user shows it.
- **Added:** table extensions. `Components::extend('{blueprint}.table', fn (SchemaPatch $patch) => ...)` patches a blueprint's table wherever it is resolved (`TableManager::extended()`).
- **Changed:** properties that shape the query (`searchable`, `sortable`, `sort_column`, `hideable`, `visible`, `visible_from`, `exportable`) refuse closures. Computed columns must be searched through `searchable([...fields])` and sorted through `sortColumn()`.

- **Changed:** the listbox and date picker now run on gcego/chroma's shared controls script (`table-ui.js` is removed); the date picker gains month and year views.

- **Added:** `Table::Poll` (5–3600 seconds) makes a table live: it refreshes while visible and shows a "Live" indicator.
- **Changed:** every native control is replaced by an accessible custom one: `<x-gcego-tables::checkbox>` (real checkbox drawn as a themed box, with an indeterminate state), `<x-gcego-tables::listbox>` (WAI-ARIA listbox: arrows, Home/End, type-ahead, Esc; single and multiple choice) for select, yes/no and page-size choices, and `<x-gcego-tables::date-picker>` (calendar popover with presets, keyboard navigation and range selection) for date and date-range filters. Their logic lives in `resources/js/table-ui.js`, inlined once per page with the CSP nonce. Filter chips show readable dates (`DateFilter::display()`).

- **Changed:** redesigned table frontend on the gcego/swatches semantic tokens. A bounded scroll area with a sticky, blurred header and themed thin scrollbars (rounded thumbs in Chromium and Safari, `scrollbar-width` in Firefox); the selection column, the identity (first) column and the row actions stay pinned while scrolling sideways, with edge shadows; native fullscreen (Fullscreen API, with an overlay fallback); a floating bulk-action pill; a selection accent bar and an indeterminate select-all; icon row actions; copy buttons on hover (always visible on touch); a toolbar with a `/` search shortcut, clear button, filter count, density switch and fullscreen; a custom multi-select dropdown for `SelectFilter::Multiple`; switch toggles in the Columns menu; grouped pagination with first/last and "Go to page"; refined empty, error and loading states.

- **Added:** `Table::Exportable` puts an **Export CSV** button in the toolbar: `TableHost::export()` streams the user's current view (search, filters, sorting, visible readable exportable columns) as formula-safe UTF-8 CSV, capped by the new `gcego-tables.export.max_rows` (10,000).
- **Fixed:** a column added to a table after a user saved a layout appeared even when declared hidden by default (`Visible` false); new columns now start with their declared default.

- **Added:** icon maps (`Badge::Icons`, `Icon::Icons`), icon values of `Icon` columns and `Icons::has()`/`svg()` take typed Luminaries icons (`Heroicon::CheckCircle`).
- Saved views degrade like preferences when their storage is unavailable (for example, before the migration runs): the failure is reported and the table keeps working.
- `Support\Formats`: the number, money, date and text formatting of columns as shared helpers (used by gcego/infolists); columns delegate to it, output unchanged.

## 0.1.0 - 2026-10-17

First release.

- `Table` blueprint part with columns, filters and gcego/actions actions; Search, Pagination, default Sort and Tailwind configuration.
- Columns: `Text`, `Number`, `Money` (ISO 4217, minor units with bcmath), `Boolean`, `Date`, `DateTime`, `Badge`, `Icon`, `Image`, `Link`, `Progress`; `Format` custom formatters; `Access` column read rules.
- Filters: `SelectFilter` (static, enum, Callback or scoped `Relationship` options), `BooleanFilter`, `DateFilter`, `DateRangeFilter`, `CustomFilter` with `FilterQuery`.
- Queries through gcego/data: per-user effective sources (search, sortable, filterable allowlists, eager loading, pagination).
- Livewire `TableHost` (`gcego-table`): search, filters, sorting, pagination (pages, simple, cursor), selection, row and bulk actions through a channel-scoped ActionHost, column visibility/order/density, preferences, saved views, optional URL state, empty/loading/error states.
- Preferences and saved views with database repositories and a publishable migration.
- Export contract (`TableExport`, `ExportWriter`) with a formula-safe `CsvWriter`.
- `TableInspector` diagnostics.
- gcego/actions additions: `ActionManager::allowsLoaded()`, ActionHost `channel` / `showButtons` and the `gcego-actions-run` request.
