# Presets

Presets are named, literal class lists in `src/Presets/DefaultPresets.php`.
That file is scanned by Tailwind, so every preset class is in the production CSS.

| Group | Presets |
| --- | --- |
| surface | `surface.page`, `surface.card`, `surface.panel` |
| layout | `layout.section`, `layout.header`, `layout.body`, `layout.stack`, `layout.grid` (1 → `md:` 2 → `xl:` 3 columns) |
| typography | `typography.title`, `typography.heading`, `typography.description`, `typography.label` |
| interaction | `interaction.focus-ring` (visible 2px outline on `:focus-visible`), `interaction.transition` (`motion-safe:` only) |
| state | `state.disabled` (`disabled:` and `aria-disabled:`), `state.loading` (`data-loading:`), `state.selected` (`aria-selected:` and `aria-current:`), `state.invalid` (`aria-invalid:`) |
| tone | `tone.success`, `tone.warning`, `tone.danger`, `tone.info` |
| control | `control.button` (+ `.primary`, `.secondary`, `.destructive`), `control.input` |
| data | `data.table`, `data.table.head`, `data.table.header-cell`, `data.table.row`, `data.table.cell` |
| navigation | `navigation.item` |

Interactive presets have a visible focus outline, `min-h-10` (40px, above the
WCAG 2.2 target-size minimum of 24px), and transitions only under
`prefers-reduced-motion: no-preference`. Tests enforce this.

Presets contain no `dark:` classes. Colors come from tokens, which change with
the mode.

## Use

```php
$styling = app(ChromaManager::class);

$styling->preset('control.button');           // ClassList, with the default theme's overrides
$styling->preset('control.button', 'ocean');  // with another theme's overrides
new SlotDefinition('action', 'control.button.primary');
```

## Adding presets (packages)

```php
$this->app->make(ChromaManager::class)->definePreset('reports.chart', ['rounded-gcego', 'border', 'border-gcego-border', 'p-4']);
```

Ids use lowercase segments separated by `.` or `-`. A duplicate id throws
`StyleConflictException` unless you pass `override: true`. Keep preset classes
in your package's `src/Presets/` directory so Tailwind finds them (see
[build integration](build-integration.md)).
