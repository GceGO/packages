# Styling

## Slots

| Component | Slots |
| --- | --- |
| `Widget` (all widgets) | `Tailwind::Wrapper`, `Header`, `Title`, `Description`, `Body`, `Footer`, `Widget::ValueSlot`, `TrendSlot`, `ChartSlot`, `LegendSlot`, `EmptySlot`, `LoadingSlot` |
| `Stats` | `Tailwind::Wrapper`, `Tailwind::Title` |

```php
new Stat('Students', …, new Tailwind(
    new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'p-6', 'shadow-sm']),
    new Slot(Stat::ValueSlot, ['text-3xl', 'font-bold', 'tracking-tight', 'text-slate-900']),
))
```

## Presets and theming

| Topic | Details |
| --- | --- |
| Where classes live | `Presets\WidgetPresets`, scanned by `gcego.css`. Tones, grids and heights come from fixed maps, so every class is known at build time. |
| Merging | Done by gcego/chroma. |
| Dark mode | Colors are gcego tokens (`bg-gcego-surface`, `text-gcego-text-muted`), so dark mode follows the theme. Charts read the same tokens. |

## Layout metadata

`Span` (1–4 or `full`), `Height` (sm, md, lg, auto) and `MinWidth` (xs–lg)
are exposed through `Widget::layout()` and the registry metadata for
gcego/layouts.
