# Architecture

```
new Layout('dashboard', …)        immutable definition: no queries, rendering or Livewire at construction
   │  gcego/registry (kind "layout")   or a part of a Blueprint (layouts are part containers)
   ▼
LayoutManager::render('dashboard')    resolve → authorize (gcego/security, deny by default)
   ▼
gcego/runtime ──► LayoutRenderer (every LayoutComponent)
                    ├─ children render first, each with its own renderer:
                    │     widgets (gcego/widgets), tables, forms, infolists, actions, other layouts
                    ├─ children that render nothing leave nothing (no empty cells or wrappers)
                    ├─ regions (tabs, split regions, header actions) are placed by their parent
                    └─ Blade view (gcego-layouts::layouts.*) + styling slots (gcego/chroma)
```

## Composition

Layout components extend `LayoutComponent`, which is a gcego/morfaw
`Component` and a gcego/blueprints `PartContainer`. That means:
- **Blueprint parts stay parts.** A `Table`, `Form`, `Infolist` or `Action`
  nested in a layout inside a `Blueprint` still counts as that blueprint's
  part (`$blueprint->part('table')` finds it). Their Livewire hosts resolve
  them from the registry as before.
- **Widgets need no registry entry of their own.** A widget inside a
  registered layout or blueprint renders through its owner and inherits the
  owner's requirements down to its path. Interactive widgets mount
  `WidgetHost` with the owner identity and a locked path.
- **Standalone layouts have no Source.** A registered `Layout` holds
  widgets, layouts and static components. Tables, forms, infolists and
  actions need a blueprint's Source, so a `Layout` that contains them is
  refused at registration. Put the layout in a `Blueprint` instead.
- **Forms and infolists keep their own layouts.** Their children must be
  their own components (`Gcego\Forms\Layout\Section`,
  `Gcego\Infolists\Layout\Grid`), and validation refuses anything else. Use
  gcego/layouts around them, not inside them.

## Rendering

| Entry point | Authorization | Mode |
| --- | --- | --- |
| `LayoutManager::render($identity, $record, $live)` | the layout's requirements | static, or Livewire children with `$live` |
| `<x-gcego-layouts::layout name="…" :record="…" :live="true" />` | same | Livewire children by default |
| A layout inside a blueprint (`Runtime::renderAuthorized('blueprint:…')`, `<livewire:gcego-blueprint>`) | the blueprint's | the blueprint render's |
| `LayoutManager::renderLayout($component)` / `Runtime::render($component)` | trusted server code (root not authorized) | static or Livewire |

Notes:
- **Livewire hosts.** "Livewire children" means children that need a host
  mount one: widgets with a date range or refresh, infolists with record
  actions, tables, forms and actions. Layouts themselves never need
  Livewire state.
- **Record.** A record key passed to `render()` goes into the render
  metadata, where infolists and record widgets read it.

## Livewire compatibility

| Concern | Behavior |
| --- | --- |
| Morph keys | In Livewire renders every layout element, placed child and region has a `wire:key` derived from its path (stable across renders). |
| Child hosts | Each child host is mounted once, with its own key. Re-rendering the surrounding component keeps the same hosts: nothing is remounted. |
| Browser state | Tab panels, tab buttons and `<details>` elements carry `wire:ignore.self`, so the open tab and expanded items survive Livewire updates while their content still morphs. |
| Form input | Child hosts own their state, so a layout update never resets form input. |

Pages, panels and modules (future packages) own page-level orchestration;
this package has no Livewire component of its own.
