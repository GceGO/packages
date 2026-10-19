# gcego/layouts

Declarative layouts for GCEGO Admin Studio. Compose pages from GCEGO
components in PHP: no Blade layout markup, CSS grid declarations,
breakpoint logic or Livewire wrappers. It provides:
- **Structure:** containers, responsive grids and spans, rows, stacks,
  sections, cards, split and sidebar layouts.
- **Disclosure:** accessible tabs, accordions and collapsible regions.
- **Utilities:** dividers, spacers, scroll areas, sticky regions, empty and
  loading states.

```php
use Gcego\Layouts\Components\{Grid, Section};
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Layout;

// app/GCEGo/Layouts/Dashboard.php — discovered as "layout:dashboard"
return new Layout('dashboard', new Permission('dashboard.view'),
    new Section('Overview',
        new Grid(
            new Columns(3),
            new Stat('Students', StudentCountProvider::class),
            new Stat('Teachers', TeacherCountProvider::class),
            new Stat('Subjects', SubjectCountProvider::class),
        ),
    ),
    new Section('Enrolment trends',
        new Chart(new Property(Chart::Name, 'enrolment'), new DataProvider(StudentEnrolmentProvider::class)),
    ),
);
```

```blade
<x-gcego-layouts::layout name="dashboard" />
```

## Examples

```php
new Grid(new Stat(…), new Stat(…), new Stat(…))             // 1 column, 2 from sm, 3 from lg
new Grid(new Columns(['default' => 1, 'md' => 2, 'xl' => 4]), …)
new Stat('Revenue', …, new ColumnSpan(2))                     // any child of a grid
new Grid(new Property(Grid::MinItemWidth, 'sm'), …)           // as many 16rem columns as fit

new Section('Academic overview', 'This term so far', new Actions(new Action('export', 'Export')), new Grid(…), new Footer(…))
new Card('Fees', new Property(Card::Variant, 'warning'), …)
new Row(new Align(justify: 'between'), …)
new Stack(new Gap(['default' => 4, 'lg' => 8]), …)
new Container(new Property(Container::MaxWidth, '5xl'), …)

// in a Blueprint (tables, forms, infolists and actions need its Source)
new Split(
    new Main(new Table(new Text('name', 'Name'), new Text('status', 'Status'))),
    new Aside('Recent activity', new ActivityFeed('Activity', new ActivitySource(ActivityLog::class, title: 'description', time: 'created_at'))),
)
new Sidebar(new Aside('Filters', …), new Main(…))
new Tabs(new Tab('Profile', new Infolist(…)), new Tab('Results', new Table(…)))
new Accordion(new AccordionItem('Fees', …), new AccordionItem('Transport', …))
new Collapsible('Advanced details', …)

new Divider()   new Spacer(8)   new ScrollArea('Audit log', …)   new Sticky(…)
new EmptyState('No students yet', 'Import a class list to get started.', new Create)
new Loading(new Property(Loading::Variant, 'card'))

new Section('Academic overview', new Grid(…), new Tailwind(
    new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'p-6', 'shadow-sm']),
))
```

## Installation

The package is a path repository in this monorepo (`gcego/layouts: ^0.1`).
It is discovered automatically through Laravel package discovery. Add
`layouts` to your CSS `@source` list (gcego/chroma's `gcego.css` already
includes it).

To publish the configuration and views:

```bash
php artisan vendor:publish --tag=gcego-layouts-config
php artisan vendor:publish --tag=gcego-layouts-views
```

## Documentation

| Topic | |
| --- | --- |
| [Architecture](docs/architecture.md) | definitions, composition, rendering, Livewire |
| [Grids](docs/grids.md) | grids, columns, spans, rows, stacks, containers |
| [Responsive](docs/responsive.md) | breakpoints, responsive values, gaps, alignment, visibility |
| [Sections](docs/sections.md) | sections, actions, footers, empty text, collapsible sections |
| [Cards](docs/cards.md) | cards and variants |
| [Split layouts](docs/split-layouts.md) | split and sidebar layouts, sticky regions, scroll areas |
| [Tabs and disclosure](docs/tabs.md) | tabs, accordions, collapsible regions |
| [Accessibility](docs/accessibility.md) | semantics, keyboard, focus, reading order |
| [Styling](docs/styling.md) | slots, presets, Tailwind overrides, utility components |
| [API reference](docs/api-reference.md) | everything, plus security and diagnostics |

## Testing

```bash
composer test     # Pest: unit, integration (rendering, composition), Livewire, diagnostics, performance
composer analyse  # PHPStan (max)
```
