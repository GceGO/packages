# Styling

## Slots

Every component declares its slots through gcego/chroma; unknown slots
fail in strict mode.

| Component | Slots |
| --- | --- |
| Every component | `Tailwind::Wrapper` (`root`) |
| `Layout` | `root`, `header`, `title`, `description` |
| `Section`, `Card` | `root`, `header`, `title`, `description`, `icon`, `actions`, `body`, `footer`, `empty`, `summary` |
| `Tabs` | `root`, `Tabs::TabListSlot`, `Tabs::TabSlot`, `Tabs::PanelSlot` |
| `Tab` | `root` (its panel) |
| `Accordion` | `root`, `Accordion::ItemSlot`, `Accordion::SummarySlot` |
| `AccordionItem` | `root`, `body` |
| `Collapsible` | `root`, `Collapsible::SummarySlot`, `description`, `body` |
| `EmptyState` | `root`, `icon`, `title`, `description`, `actions` |
| `Loading` | `root`, `Loading::SkeletonSlot` |

```php
new Section('Academic overview', new Grid(…), new Tailwind(
    new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'p-6', 'shadow-sm']),
    new Slot(Tailwind::Body, ['p-6', 'space-y-6']),
))
new Grid(…, new Tailwind(new Slot(Tailwind::Wrapper, ['gap-10'])))   // replaces the computed gap
```

## How classes combine

A layout's computed classes (structure, columns, gaps, variants) are the
base. The slot's classes are merged over them by gcego/chroma's merger, in
this order:
1. presets;
2. theme;
3. app overrides (`ChromaManager::override()`);
4. `Tailwind` nodes.

So any computed class can be replaced by a conflicting one (`gap-10`
replaces `gap-6`; `md:grid-cols-5` replaces `md:grid-cols-…`). Variants
merge separately: `p-6` replaces the base padding, not `sm:p-6`.

## Design

| Topic | Details |
| --- | --- |
| Tokens | Colors, radii and shadows are gcego tokens (`bg-gcego-surface`, `border-gcego-border`, `shadow-gcego-card`, `rounded-gcego-lg`), so themes and dark mode apply everywhere. |
| Where classes live | Literal classes are in `Presets\LayoutPresets` and the views, both scanned by `gcego.css` (`layouts/src/Presets`, `layouts/resources/views`). |
| Views | Override them in `resources/views/vendor/gcego-layouts` (`php artisan vendor:publish --tag=gcego-layouts-views`). |

## Empty and loading states

```php
new EmptyState('No students yet', 'Import a class list to get started.', new Property(EmptyState::Icon, 'users'), new Create)
new Loading()                                        // three text lines
new Loading(new Property(Loading::Variant, 'card'))  // card, grid (three tiles), table (Lines rows)
```

Both are structural placeholders with stable dimensions. Loading is a
polite status and EmptyState a dashed panel. Its children are actions.
Neither runs application loading logic.
