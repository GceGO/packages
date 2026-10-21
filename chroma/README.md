# gcego/chroma

The Tailwind CSS v4 styling engine of GCEGO Admin Studio. Components describe
*what* they are; this package decides *how they look*: non-color design tokens
(radius, shadows, typography, motion), presets, named slots, conflict-aware
class merging and the Tailwind build integration. Colors, color themes, dark
mode, accents and tenant branding come from [gcego/swatches](../swatches),
which Chroma requires and registers.

```php
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;

new Card('Enrolments',
    new Tailwind('rounded-2xl', 'shadow-sm'),                      // shorthand: the root slot
);

new Card('Enrolments',
    new Tailwind(
        new Slot(Tailwind::Wrapper, [
            'rounded-2xl',
            'border-slate-200',
        ]),
        new Slot(Tailwind::Body, [
            'grid',
            'gap-4',
            'md:grid-cols-2',
        ]),
        Slot::remove(Tailwind::Wrapper, ['shadow-gcego-card']),
    ),
);
```

With no `Tailwind` node at all, every component still renders with
professional, accessible defaults that support light and dark mode.

## Requirements

- PHP 8.3+, Laravel 13, Livewire 4 (through gcego/runtime)
- `gcego/morfaw` and `gcego/runtime` ^0.1
- Tailwind CSS **v4**, installed by the application (`tailwindcss`, `@tailwindcss/vite`).
  This package ships CSS sources and adds no npm dependencies, CDN links or second CSS framework.

## Installation

```bash
composer require gcego/chroma
php artisan vendor:publish --tag=gcego-chroma-config   # optional
```

The provider is auto-discovered. It registers the `gcego/chroma` package with
gcego/core (capabilities `styling` and `styling.tailwind`) and replaces the
runtime's `NullStylingResolver`. A resolver bound by your application is left alone.

### 1. CSS (`resources/css/app.css`)

```css
@import "tailwindcss";
@import "../../vendor/gcego/chroma/resources/css/gcego.css";
```

### 2. Layout (`<head>`)

```blade
<html {{ app(\Gcego\Chroma\ChromaManager::class)->rootAttributes() }}>
<head>
    <x-gcego-chroma::appearance />   {{-- before stylesheets: no flash of the wrong mode --}}
    <x-gcego-chroma::theme />        {{-- custom themes only; outputs nothing for "gcego" --}}
    @vite(['resources/css/app.css'])
</head>
```

Then build with `npm run build` (or `npm run dev`). See
[build integration](docs/build-integration.md) for the exact `@source` paths.

## Precedence (lowest first)

1. framework preset (for example `surface.card`)
2. theme preset override
3. component package slot classes
4. application overrides (`config/gcego-chroma.php` and `ChromaManager::override()`)
5. the instance's `Tailwind` nodes, in order

Later layers merge with conflict awareness: `p-6` replaces `p-4`, `px-2` refines
`p-4`, `md:p-8` is independent of `p-4`, and unknown utilities are never dropped.

## Documentation

- [Architecture](docs/architecture.md)
- [The Tailwind node](docs/tailwind.md)
- [Slots and precedence](docs/slots.md)
- [Design tokens](docs/tokens.md)
- [Themes](docs/themes.md)
- [Presets](docs/presets.md)
- [Dark mode](docs/dark-mode.md)
- [Build integration](docs/build-integration.md)
- [API reference](docs/api-reference.md)

## Development

```bash
composer test        # Pest: unit, integration and real Tailwind builds
composer analyse     # PHPStan (level max, Larastan)
composer format      # Pint
composer benchmark   # benchmarks/run.php
composer build-css   # compile resources/css/build-entry.css with the monorepo's Tailwind
```

Build tests need Node.js and the monorepo's `node_modules/@tailwindcss/node`
and `@tailwindcss/oxide`; they are skipped, and reported as skipped, otherwise.

## License

MIT. See [LICENSE.md](LICENSE.md).
