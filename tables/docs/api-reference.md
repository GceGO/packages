# API reference

Namespace `Gcego\Tables`.

## Table

`Table` (BlueprintPart, role `table`).

**Properties:**

| Property | Default | Meaning |
| --- | --- | --- |
| `Name` | `table` | the table's name |
| `Label` | | caption and heading |
| `Description` | | text under the heading |
| `MultiSort` | | allow sorting by several columns |
| `Selectable` | on when there are bulk actions | row checkboxes |
| `Density` | `comfortable` | or `compact` |
| `UrlState` | | keep state in the URL |
| `EmptyHeading`, `EmptyDescription` | | empty-state text |
| `ColumnManager` | `true` | the Columns menu |
| `SavedViews` | `true` | the Views menu |
| `Exportable` | `false` | the Export CSV button (see [actions](actions.md#export)) |
| `Poll` | | refresh every N seconds (5–3600) while visible, with a Live indicator |

**Children:**
- columns;
- filters;
- gcego/actions `Action`s.

**Configuration nodes:**
- `Configuration\Search`;
- `Configuration\Pagination`;
- `Gcego\Data\Sorting\Sort`;
- `Gcego\Chroma\Tailwind`.

**Slots:** `Tailwind::Wrapper`, `Tailwind::Header`, `Tailwind::Footer`,
plus these table constants: `Toolbar`, `SearchInput`, `Filters`,
`TableElement`, `TableHead`, `HeaderCell`, `TableBody`, `Row`, `Cell`,
`Pagination` and `EmptyState`.

## Columns and filters

See [columns](columns.md) and [filtering](filtering.md).

| Group | Classes |
| --- | --- |
| Columns | `Columns\Column` (abstract) and its subclasses `Text`, `Number`, `Money`, `Boolean`, `Date`, `DateTime`, `Badge`, `Icon`, `Image`, `Link`, `Progress` |
| Filters | `Filters\TableFilter` (abstract) and its subclasses `SelectFilter`, `BooleanFilter`, `DateFilter`, `DateRangeFilter`, `CustomFilter` |
| Filter helpers | `FilterQuery`, `RelationshipOptions` |
| Column configuration | `Configuration\Format(Closure\|class-string<ColumnFormatter>)`, `Configuration\Access(?Requirement $read, bool $mask = false)` |

## TableManager (singleton)

| Method | Returns |
| --- | --- |
| `resolve(string $definition)` | `Query\ResolvedTable` (`definition`, `blueprint`, `table`, `path`, `source`, `schema`, `identity()`) |
| `authorize(ResolvedTable)` | `void`; throws `AuthorizationException` |
| `schema(Table)` | `Schema\TableSchema` (cached) |
| `access(TableSchema)` | `Query\ColumnAccess` (`readable`, `masked`, `canRead()`, `isMasked()`) |
| `normalizer(TableSchema, ColumnAccess, bool $strict = true, ?class-string $model = null)` | `State\TableStateNormalizer` (`normalize()`, `defaults()`, `perPageOptions()`, `searchable()`, `filterAllowed()`, `$dropped`) |
| `query(ResolvedTable, ColumnAccess, TableState)` | `Gcego\Data\Results\DataResult` |
| `input(TableSchema, TableState)`, `context(TableSchema, TableState)` | gcego/data `QueryInput`, `QueryContext` |
| `export(string $definition, array $state = [], bool $visibleOnly = true)` | `Export\TableExport` (`columns`, `headings()`, `rows(?int $limit)`) |
| `model(ResolvedTable)` | `?class-string<Model>` |
| `owner()` | `?Support\TableOwner` |
| `inspect(string\|Table)` | `Diagnostics\TableReport` |

## State

`State\TableState` (readonly):
- properties `search`, `filters`, `sorts`, `page`, `perPage`, `cursor`, `order`, `hidden`, `density`;
- methods `visibleColumns()`, `isFiltered()`, `sortDirection()`, `sortPosition()`, `toView()`, `toPreferences()`;
- constant `Version`.

## Contracts

| Interface | Methods |
| --- | --- |
| `TableComponent` | marker for columns and filters |
| `ColumnFormatter` | `format(mixed $value, mixed $record, Column $column): string\|int\|float\|null` |
| `PreferenceStore` | `get`, `put`, `forget`, `forgetOwner` |
| `SavedViewRepository` | `all`, `find`, `create`, `update`, `delete`, `setDefault`, `forgetOwner`, `count`, `nameTaken` |
| `ExportWriter` | `write(TableExport, resource): int` |

## Other classes

| Class | Purpose |
| --- | --- |
| `Livewire\TableHost` | alias `gcego-table` |
| `Actions\TableActions` | `row()`, `bulk()`, `offeredBulk()`, `forRecord()`, `isRowAction()`, `isBulkAction()` |
| `Selection\Selection` | `from(keys, max)`, `with()`, `without()`, `has()`, `containsAll()` |
| `Preferences\ColumnPreferences`, `Views\SavedViewManager`, `Views\SavedView` | preferences and saved views |
| `Rendering\TableRenderer`, `ColumnRenderer`, `FilterRenderer` | runtime renderers |
| `Rendering\TableRenderContext`, `TableRenderFrame`, `TableRows` | render state |
| `Query\EffectiveSources` | per-user effective sources |
| `Support\Icons`, `SafeUrl`, `ValueResolver`, `TableOwner`, `JsonState` | helpers |
| `Export\CsvWriter` | formula-safe CSV |
| `Diagnostics\TableInspector` | inspection |

Diagnostic checks:
- `schema`, `source`, `effective`, `relations`, `hidden`;
- `actions`, `authorization`;
- `renderer`, `styling`, `storage`, `performance`.

## Configuration (`config/gcego-tables.php`)

| Key | Default |
| --- | --- |
| `pagination.default_per_page` | 25 |
| `pagination.max_per_page` | 100 |
| `pagination.options` | `[10, 25, 50, 100]` |
| `search.debounce_ms` | 350 |
| `search.max_length` | 100 |
| `selection.max_records` | 500 |
| `preferences.enabled` | true |
| `saved_views.enabled` | true |
| `saved_views.max_per_table` | 25 |
| `storage.connection` | null |
| `storage.preferences_table` | `gcego_table_preferences` |
| `storage.views_table` | `gcego_table_views` |
| `dates.timezone` | null (app.timezone) |
| `export.chunk` | 100 |
| `export.max_rows` | 10000 |

## Exceptions (`Exceptions\TableException`)

| Exception | When |
| --- | --- |
| `InvalidTableException` | definition errors |
| `InvalidTableStateException` | untrusted state; HTTP 400 |

## Service provider

`TablesServiceProvider` (`gcego/tables` package descriptor):
- publish tags `gcego-tables-config`, `gcego-tables-views` and `gcego-tables-migrations`;
- views and translations under `gcego-tables::`.

## gcego/actions additions

- **`ActionManager::allowsLoaded(ResolvedAction, mixed $record): bool`:**
  display-time authorization for a loaded row.
- **`ActionHost` mount parameters:** `channel` and `showButtons`.
- **The `gcego-actions-run` event:** `channel`, `action`, `record` or `keys`.
