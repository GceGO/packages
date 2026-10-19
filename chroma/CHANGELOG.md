# Changelog

## Unreleased

- **Added:** shared Alpine.js form controls (`resources/js/controls.js`: `gcegoListbox`, `gcegoDatePicker` with date, range, date-time and time modes), emitted by `<x-gcego-chroma::controls />` / `ChromaManager::controlsTag()` and used by gcego/forms and gcego/tables.

- The build-size budget test reflects the whole framework as of 2026-10-18: 76 KB raw and a new 13 KB gzip limit (gcego/layouts' responsive matrix is ~17 KB of it).

- **Changed (breaking):** colors moved to gcego/swatches. Chroma requires and registers it; `gcego.css` imports `swatches.css`. `DefaultTokens` now holds only radius, shadows, font and duration, and `TokenType::Color` was removed. Color tokens in Chroma themes are refused with a pointer to `config/gcego-swatches.php`. `Appearance\*` and `Tokens\Contrast` were removed (use `Gcego\Swatches\Themes\*` and `Gcego\Swatches\Accessibility\Contrast`). `gcego-chroma.appearance` and `gcego-chroma.accents` moved to `gcego-swatches.*` (still honoured as a deprecated fallback). `gcego-chroma.theme` defaults to null (follows `gcego-swatches.theme`). `resources/js/appearance.js` moved to Swatches.
- **Changed:** `ChromaManager`'s color methods delegate to `SwatchesManager`, plus `brandTag()` and `swatches()`. `themeTag()` combines the Swatches theme CSS with Chroma's non-color overrides. The theme registry knows every Swatches theme. `ChromaInspector` checks contrast with Swatches' validator (56 pairs per mode). `UtilityGroups` recognizes canonical color utilities (`bg-surface`, `text-muted-foreground`). The color explorer previews Chroma's real presets.

- **Added:** main colors users pick for themselves (`gcego-chroma.accents`): nine presets (`Appearance\Accents`: teal, blue, indigo, violet, rose, red, orange, amber, green), each readable (4.5:1) in light and dark mode. `ChromaManager::accents()`, `accentTag()` (their styles, plus a swatch of the theme's own color), and `GcegoAppearance.accent()` / `accents()` / `setAccent()` in the appearance controller, applied before the first paint and stored in localStorage (`gcego.accent`). An element marked `data-gcego-accent-swatch="red"` shows that color through the primary tokens.
- Fixed: single-axis utilities no longer override each other when merging: `gap-x-*` and `gap-y-*`, `overflow-x-*` and `overflow-y-*`, `inset-x-*` and `inset-y-*` are separate groups (the two-axis forms still override both).
- Fixed: `outline-offset-*` and `ring-offset-*` are their own groups instead of outline and ring colors.
- Fixed: arbitrary variants containing colons (`[&::-webkit-details-marker]:hidden`) are valid classes; empty variants are still refused.
- The build-size budget test now covers gcego/layouts presets (54.4 KB minified, 9.5 KB gzipped for all packages).

## 0.1.0 - 2026-10-17

First release.

- `Tailwind` configuration node with shorthand classes and `Slot`s (merge, replace and remove modes).
- Class normalization, validation and conflict-aware merging with variant, important and shorthand awareness.
- 22 design tokens as CSS custom properties, exposed as `gcego-*` Tailwind utilities through `@theme inline`.
- Themes with validated light and dark token overrides and preset overrides.
- Default presets for surfaces, layout, typography, interaction, state, tone, controls, tables and navigation.
- `SlotRegistry` and `SlotResolver`, the gcego/runtime `StylingResolver`, with five-layer precedence and caching.
- Light, dark and system appearance with an inline, CSP-nonce-aware controller.
- `gcego.css` entry point with `@source` paths for GCEGO packages, and real-build tests.
- `ChromaInspector` diagnostics, including WCAG 2.2 AA contrast checks per theme and mode.
