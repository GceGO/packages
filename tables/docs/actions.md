# Actions

Put gcego/actions actions in the table, or anywhere in the blueprint. Each
is placed by its own placement:

| Placement | Shown as | Examples |
| --- | --- | --- |
| `header`, `toolbar` | buttons in the table header (an ActionHost) | `Create`, custom |
| `row` | buttons in each row | `View`, `Edit`, `Delete`, `Restore`, custom |
| `bulk` | the bulk bar when rows are selected | `BulkDelete`, `BulkRestore`, custom `BulkAction` |

```php
new Table(
    new Text('name', 'Subject Name'),
    new Create(), new Edit(), new Delete(), new BulkDelete(),
    new Action('archive', 'Archive', new Property(Action::Ability, 'archive'), new Property(Action::Record, true),
        new Property(Action::Placement, 'row'), new Handler(ArchiveSubject::class)),
)
```

## How it works

| Step | Details |
| --- | --- |
| Display | Row buttons are decided per loaded row with `ActionManager::allowsLoaded()`: policies on the record already loaded through the scoped source, with no extra query. Trash-only actions (Restore, ForceDelete) appear on trashed rows only. Bulk actions appear when the user may run them on at least one visible row. |
| Click | The table validates that the name is one of its row or bulk actions, then sends `gcego-actions-run` (with its channel, the action, and the record key or selection) to a button-less ActionHost on that channel. |
| Execution | ActionHost runs the action through gcego/actions: scoped record resolution, re-authorization, signed single-use confirmation tokens, input dialogs (Edit and Create show the gcego/forms form), transactions and bulk policies. Hidden or forged actions are refused at execution time too. |
| Afterwards | `gcego-action-completed` refreshes the table and clears the selection on success. |

The table never executes actions itself.

## Export

Turn on the toolbar's **Export CSV** button per table:

```php
new Table(new Property(Table::Exportable, true), …)
```

It downloads exactly what the user is looking at: the current search,
filters and sorting, and the visible, readable, exportable columns, as
formula-safe UTF-8 CSV (with a BOM for spreadsheets), at most
`gcego-tables.export.max_rows` rows (10,000). `TableHost::export()`
re-checks the blueprint's requirements and column access, and refuses
tables that don't offer export.

For your own code, `TableManager::export($definition, $state)` returns a `TableExport` for
the current user:
- authorized now;
- filters, search and sorting applied;
- readable, exportable columns only;
- rows fetched in chunks with no COUNT.

Write it with `Export\CsvWriter`, or any `Contracts\ExportWriter`.
Queued exports should rebuild it inside `SecurityManager::runOperation()`,
and downloads should call `SecurityManager::authorizeDownload()`. The
queue and download layers belong to later packages.
