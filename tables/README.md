# gcego/tables

Declarative data tables for GCEGO Admin Studio. You declare columns,
filters and actions; the package provides:

- the Livewire host, search, filters, sorting and pagination;
- row selection, plus row, header and bulk actions;
- column visibility, ordering and density, with per-user preferences;
- saved views, and CSV export of the current view (`Table::Exportable`);
- accessible Tailwind markup.

Data comes through gcego/data, and gcego/security decides what each user
may see.

```php
use Gcego\Blueprints\Blueprint;
use Gcego\Data\Source;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Table;
use Gcego\Tables\Columns\{Text, Boolean};
use Gcego\Actions\{Create, Edit, Delete};
use Gcego\Forms\Form;
use Gcego\Forms\Fields\TextInput;

new Blueprint('subjects',
    new Ability('viewAny', Subject::class),
    new Source(Subject::class),
    new Form(new TextInput('name', 'Subject Name'), new TextInput('code', 'Code')),   // input for Create and Edit (gcego/forms)
    new Table(
        new Text('name', 'Subject Name', new Property(Text::Searchable, true), new Property(Text::Sortable, true)),
        new Text('code', 'Code'),
        new Boolean('is_published', 'Published'),
        new Create(),
        new Edit(),
        new Delete(),
    ),
);
```

```blade
<livewire:gcego-table definition="subjects" />
```

Rendering the blueprint through gcego/runtime mounts the same host.

## Developer experience

```php
// Minimal
new Table(new Text('name', 'Subject Name'), new Text('code', 'Code'));

// Property syntax
new Table(new Text(new Property(Text::Name, 'name'), new Property(Text::Searchable, true), new Property(Text::Sortable, true)));

// Configuration
new Table(new Text('name', 'Subject Name', new Property(Text::Searchable, true)), new Search(), new Pagination(25));

// Actions (placed by gcego/actions placement: header, row or bulk)
new Table(new Text('name', 'Subject Name'), new Boolean('is_published', 'Published'), new Edit(), new Delete());

// Tailwind slots
new Table(new Text('name', 'Subject Name'), new Tailwind(
    new Slot(Tailwind::Wrapper, ['overflow-hidden', 'rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'shadow-sm']),
    new Slot(Table::TableHead, ['bg-slate-50']),
    new Slot(Table::Row, ['hover:bg-slate-50', 'transition-colors']),
));
```

You never write the following yourself; the framework provides them:
- query controllers, pagination, sorting or search handlers;
- Livewire components or Blade tables;
- selection handling, action dispatch or preference storage.

## Installation

The package is required as a path repository in this monorepo (`gcego/tables: ^0.1`) and is auto-discovered. For preferences and saved views, publish and run the migration:

```bash
php artisan vendor:publish --tag=gcego-tables-migrations
php artisan migrate
```

Optional publish tags:
- `gcego-tables-config`;
- `gcego-tables-views`.

Add `tables` to the `@source` brace list of your CSS if it isn't there yet. gcego/chroma's `gcego.css` already includes it.

## Documentation

| Topic | |
| --- | --- |
| [Architecture](docs/architecture.md) | how definitions, state, gcego/data, rendering and Livewire fit |
| [Columns](docs/columns.md) | Text, Number, Money, Boolean, Date, DateTime, Badge, Icon, Image, Link, Progress, custom formatters |
| [Searching](docs/searching.md), [Filtering](docs/filtering.md), [Sorting](docs/sorting.md), [Pagination](docs/pagination.md) | query controls |
| [Selection](docs/selection.md), [Actions](docs/actions.md) | rows, bulk, header and row actions |
| [Preferences](docs/preferences.md), [Saved views](docs/saved-views.md) | per-user layout and views |
| [Livewire](docs/livewire.md) | the host, state and URL sync |
| [Security](docs/security.md) | the threat model and how it is enforced |
| [API reference](docs/api-reference.md) | every class and option |

## Security in one paragraph

**Definitions and sources:** tables only exist inside registered
blueprints. The browser names a blueprint identity, never a class or
model. Every request re-checks the blueprint's gcego/security
requirements. Every query runs through gcego/data as the current user,
so policies, tenant scopes and mandatory constraints apply.

**Columns:** search, sort and filter keys must be declared on columns and
filters. Unreadable columns (`Access`) are removed from rendering, search,
sorting, filters and exports. Preferences and saved views cannot bring
them back.

**Rows, keys and actions:** values are escaped, and URLs and icons are
validated. Row keys and selections are untrusted: gcego/actions
re-resolves every key in scope and re-authorizes it before anything runs.

## Performance

| Topic | Details |
| --- | --- |
| Paging | Database-level pagination. Optional `Simple` (no COUNT) and `Cursor` modes. |
| Relations | Automatic eager loading for relation columns. Cells never lazy-load. |
| Livewire payload | Small snapshots: models never enter Livewire state. |
| Selections | Bounded at 500 rows by default. |
| Measured | A 5,000-row table mounts with 9 queries and a 910-byte snapshot (see `tests/Feature/PerformanceTest.php`). |

## Testing

```bash
composer test      # Pest (unit, integration, Livewire, database, security, performance)
composer analyse   # PHPStan (max)
composer format    # Pint
```

The default icon set's path data follows Heroicons (MIT) outline icons.
