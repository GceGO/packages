# gcego/infolists

Declarative, read-only record details for GCEGO Admin Studio.

You declare entries and layouts. The package provides:
- record resolution through gcego/data and gcego/security, tenant-scoped and authorized;
- relationship eager loading;
- safe formatting;
- semantic, accessible Blade markup with Tailwind styling;
- optional Livewire hosting with record actions from gcego/actions.

```php
use Gcego\Infolists\Infolist;
use Gcego\Infolists\Entries\{Text, Badge, DateTime};
use Gcego\Infolists\Layout\{Section, Grid};

new Blueprint('subjects',
    new Ability('viewAny', Subject::class),
    new Source(Subject::class),
    new Infolist(
        new Section('Subject Information',
            new Grid(2,
                new Text('name', 'Subject Name', new Property(Text::Copyable, true)),
                new Text('category.name', 'Category'),
            ),
            new Badge('status', 'Status'),
        ),
        new Section('Record Information',
            new DateTime('created_at', 'Created At'),
            new DateTime('updated_at', 'Last Updated'),
        ),
        new Edit(), new Delete(),          // record actions (gcego/actions)
    ),
);
```

## Showing a record

| Use | Code | Interactive |
| --- | --- | --- |
| Blade, static | `<x-gcego-infolists::infolist definition="subjects" :record="$subject->id" />` | no |
| PHP, static | `app(InfolistManager::class)->render('subjects', $id)` | no |
| Livewire, with record actions | `<livewire:gcego-infolist definition="subjects" :record="$subject->id" />` | yes |

Every form:
1. resolves the blueprint from the registry;
2. checks its requirements;
3. finds the record through the scoped source (404 when missing or out of scope);
4. checks the `view` ability (403);
5. decides entry access for the current user.

## More examples

```php
new Infolist(new Text('name', 'Subject Name'), new Badge('status', 'Status'));

new Infolist(new Section('Subject Information', new Text('name', 'Subject Name'), new Text('code', 'Subject Code')));

new Infolist(new Grid(2, new Text('name', 'Name'), new Text('category.name', 'Category')));

new Infolist(new RepeatableEntry('objectives', new Text('title', 'Objective'), new Text('description', 'Description')));

new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'), new Property(Text::Copyable, true));

new Infolist(
    new Section('Subject Information', new Text('name', 'Subject Name'), new Text('code', 'Subject Code')),
    new Tailwind(
        new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'shadow-sm']),
        new Slot(Tailwind::Body, ['p-6', 'space-y-6']),
    ),
);
```

## Contents

| Topic | |
| --- | --- |
| [Architecture](docs/architecture.md) | definitions, resolution, rendering |
| [Entries](docs/entries.md) | Text, Number, Money, Boolean, Badge, Icon, Image, Date, DateTime, Link, KeyValue, ListEntry, RepeatableEntry |
| [Formatting](docs/formatting.md) | locales, currencies, dates, Markdown/HTML, custom formatters and resolvers |
| [Relationships](docs/relationships.md) | relation paths, eager loading, tenant-scoped relations |
| [Layouts](docs/layouts.md) | Section, Grid, Tabs/Tab, Split, conditions |
| [Rendering](docs/rendering.md) | runtime integration, views, empty and error states, accessibility |
| [Livewire](docs/livewire.md) | the host and record actions |
| [Styling](docs/styling.md) | slots and presets |
| [Security](docs/security.md) | record and entry authorization, tenancy, XSS, serialization |
| [API reference](docs/api-reference.md) | every class and option |

## Requirements

- gcego/tables, for the shared formatting, icon set, safe URLs and value resolution;
- `symfony/html-sanitizer` and `league/commonmark`, both already in Laravel apps.

Add `infolists` to your CSS `@source` list if it isn't there; gcego/chroma's `gcego.css` already includes it.

## Testing

```bash
composer test      # Pest: unit, integration, database, Livewire, security, rendering, performance
composer analyse   # PHPStan (max)
composer format    # Pint
```
