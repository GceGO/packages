# Build integration (Tailwind CSS v4)

Tailwind generates only the classes it finds in scanned files. GCEGO styling
ships everything needed to make the right classes exist.

## Files

| File | Contents |
| --- | --- |
| `resources/css/gcego.css` | **the entry point**: imports tokens and theme, declares `@source` and the `dark` variant |
| `resources/css/tokens.css` | `:root` / `:root.dark` custom properties |
| `resources/css/theme.css` | `@theme inline` mapping of tokens to `gcego-*` utilities |
| `resources/css/build-entry.css` | standalone build for tests: `@import "tailwindcss" source(none); @import "./gcego.css";` |

## Your application

```css
/* resources/css/app.css */
@import "tailwindcss";
@import "../../vendor/gcego/chroma/resources/css/gcego.css";
```

The application's own sources are found by Tailwind's automatic detection, or by
your `@source` lines. In this monorepo the path is
`../../packages/chroma/resources/css/gcego.css`. Vite's `@tailwindcss/vite`
plugin builds it as usual: run `npm run build`.

## What `gcego.css` scans

```css
@source "../../../{runtime,styling,data,security,actions,forms,tables,infolists,widgets,layouts,navigation,pages,modules,panels,studio,notifications,media,workflows,settings,analytics}/resources/views";
@source "../../../{…same list…}/src/Presets";
```

- **Base directory:** `../../../` is the directory holding the GCEGO packages:
  `vendor/gcego/` for Composer installs, and `packages/` in this monorepo.
- **What is scanned:** only Blade views and preset files, never whole packages,
  tests or `vendor` trees.
- **Missing packages:** names that don't exist are ignored, so planned packages
  are listed ahead of time.
- **No wildcard:** Tailwind's Oxide scanner does **not** expand a `*` directory
  after `../`. We verified that `../../../*/resources/views` matched 0 files, so
  the names are listed explicitly.

A package outside this list, for example a third-party GCEGO extension, adds
its own lines to its CSS, or tells users to add:

```css
@source "../../vendor/acme/gcego-charts/resources/views";
@source "../../vendor/acme/gcego-charts/src/Presets";
```

## Where to write classes

| Classes written in | Found by |
| --- | --- |
| a GCEGO package's `resources/views` or `src/Presets` | `gcego.css` |
| your app's code (blueprints, `Tailwind` nodes, config) | your app's sources |
| a string built at runtime (`'grid-cols-'.$n`) | **nothing**: write full class names |

## Verification

`tests/Build/compile.mjs` runs a real build through the monorepo's
`@tailwindcss/node` and `@tailwindcss/oxide`:

```bash
composer build-css    # node tests/Build/compile.mjs resources/css/build-entry.css
```

`tests/Build/TailwindBuildTest.php` asserts the following:

- **Presets:** every default preset class compiles.
- **Scanning:** only the documented locations are scanned.
- **Tokens:** light and dark token values are emitted.
- **Variants:** responsive, `focus-visible:`, `aria-*:`, `data-*:`,
  `motion-safe` and `dark:` output are present.
- **App sources:** literal classes in app sources compile and concatenated ones
  don't (`tests/Build/app-entry.css`).
- **Size:** the minified build stays under 60 KB.

The build tests skip, with a reason, when Node or Tailwind is missing.
