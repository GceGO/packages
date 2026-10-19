# Changelog

## Unreleased

- **Added:** APCA lightness contrast (`Accessibility\Apca`, APCA-W3 0.1.9), reported next to WCAG ratios in `gcego:swatches:contrast`, in `ContrastResult::$apca`, in the explorer, and as advisory warnings with `gcego:swatches:validate --apca` (`validation.apca`).
- **Added:** color-vision simulation (`ColorVisionSimulator`: protanopia, deuteranopia, tritanopia with Machado 2009 matrices, and achromatopsia), shown in the contrast command and the explorer, with advisory warnings for collapsing status and chart colors (`--color-vision`, `validation.color_vision`).
- **Added:** colors from a logo (`LogoColorExtractor`, `Swatches::logoColors()` / `brandFromLogo()`, `gcego:swatches:logo`) for PNG, JPEG, GIF, WebP and SVG, with size, pixel and XXE protections.
- **Added:** `Build/ColorOracleTest` checks the color math against Lightning CSS (750 colors, all within ±1 per channel).
- **Changed:** built-in presets are runtime CSS for the active theme by default (`css.static_presets`), so stylesheets no longer carry every preset. `themes.css` is opt-in.
- **Changed:** `gcego-chroma.appearance` and `gcego-chroma.accents` are honoured again as a deprecated fallback instead of failing.
- **Fixed:** `Contrast::suggest()` could return a hex value that rounded to just below the requirement (`#777777` for 4.5:1 on white); it now steps on until the 8-bit value passes.

## 0.1.0 - 2026-10-18

First release. Takes over color ownership from gcego/chroma.

- `Colors\Color`: immutable color value. Parses hex, rgb()/rgba(), hsl()/hsla() (comma and space syntax), oklch(), oklab(), the 148 named colors and transparent. Converts between sRGB, HSL, OKLab and OKLCH, composites alpha, computes ΔE OK and WCAG luminance, and gamut-maps to sRGB with the CSS Color 4 algorithm. Serialization is rebuilt from numbers, never echoed.
- Palettes: ten reference families mirrored from Tailwind CSS 4.3.3 (verified against node_modules), `PaletteRegistry`, and a deterministic OKLCH `ScaleGenerator` that anchors the brand color and keeps the hue exact.
- 73 semantic color tokens with `{reference}` values: surfaces, borders and focus, brand, status with foreground and soft variants, interaction states, panel chrome, eight categorical chart colors and trend colors. The original gcego/chroma names and values are kept.
- Tailwind CSS v4 bridge (`@theme inline`): canonical utilities (`bg-surface`, `text-foreground` …) for every token, `gcego-*` utilities for the original 17 names, and the `dark:` variant.
- Themes with inheritance, cycle detection and per-mode overrides. Presets: GCEGO Default, Midnight, Slate, Ocean, Forest, all passing WCAG 2.2 AA in both modes and shipped as static CSS.
- Appearance controller (light/dark/system before first paint, localStorage only) with button delegation and `aria-pressed` sync; user accents; Blade components `appearance`, `appearance-switcher`, `theme`, `brand`, `accents`, `head`.
- Tenant branding: `Brand`, a stateless `BrandCompiler` that only emits accessible primary and accent tokens, and a scoped `BrandResolver`.
- `ContrastAuditor` and `ThemeValidator`: 56 pairs per mode, broken and circular references, inheritance errors, out-of-gamut and indistinct chart warnings, with suggested fixes.
- Commands: `gcego:swatches:list`, `themes`, `validate`, `contrast`, `palette`, `generate` (`--package`, `--check`), `cache`, `clear` (hooked into `optimize`).
- Developer color explorer (off by default, gated) with a prebuilt stylesheet.
