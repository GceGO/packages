# Styling

## Slots

| Component | Slots |
| --- | --- |
| `Infolist` | `Tailwind::Wrapper`, `Header`, `Title`, `Description`, `Body`, `Infolist::EmptyState` |
| `Section` | `Tailwind::Wrapper`, `Header`, `Description`, `Body` |
| `Entry` (all entries) | `Tailwind::Wrapper`, `Tailwind::Label`, `Entry::Value`, `Tailwind::Description` |
| Other layouts | `Tailwind::Wrapper` |

```php
new Infolist(..., new Tailwind(
    new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'shadow-sm']),
    new Slot(Tailwind::Body, ['p-6', 'space-y-6']),
));
new Text('name', 'Name', new Tailwind(new Slot(Entry::Value, ['text-base', 'font-semibold'])));
```

## Presets and dark mode

| Topic | Details |
| --- | --- |
| Where classes live | Defaults are in `Presets\InfolistPresets` (scanned by `gcego.css`). |
| Variants and tones | Badge variants and icon tones reuse `Gcego\Tables\Presets\TablePresets`. |
| No runtime classes | Every class comes from a fixed map, so Tailwind finds them at build time. |
| Dark mode | Colors use gcego tokens (`bg-gcego-surface`, `text-gcego-text-muted`, …), so dark mode follows the theme. |
