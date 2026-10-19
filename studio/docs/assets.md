# Assets

Studio builds nothing and writes no asset file. It reads `assets.css`, `assets.js` and `assets.manifest` (and `public/hot`) to report problems:

| Finding | Severity |
| --- | --- |
| a configured Vite entry missing from the manifest | failure |
| a manifest file missing from `public/build` | failure |
| no manifest and no dev server | failure (development: the Vite dev server counts) |
| CSS entry missing | warning |
| no `@import "tailwindcss"` (Tailwind v4) | warning |
| gcego/chroma's `gcego.css` not imported | warning |
| `import Alpine from 'alpinejs'` or `Alpine.start()`: Livewire 4 ships and starts Alpine | warning |
| `--production` with the dev server `hot` file present, or no build | failure |

The recommended `resources/css/app.css`:

```css
@import "tailwindcss";
@import "../../vendor/gcego/chroma/resources/css/gcego.css";
```

Vite entries for panel pages come from `gcego-panels.assets.vite`, or from `gcego-studio.assets.vite` when set. No React, Vue or Bootstrap is needed or loaded.
