# Architecture

```
Tailwind node (instance)        config/gcego-chroma.php        component package provider
        │                                │                               │
        │                       ChromaConfig ──► ThemeRegistry           ├─ defineSlots(Card::class, [...])
        │                                │        PresetRegistry ◄────────┘  definePreset('reports.chart', ...)
        ▼                                ▼        SlotRegistry
  gcego/runtime renderer ── $styles('root') ──► StylingResolver = SlotResolver
                                                     │
                                  preset → theme preset → package slot → app overrides → instance
                                                     │       (ClassMerger at every step)
                                                     ▼
                                          "rounded-gcego-lg border … p-8"
```

## Responsibilities

| Concern | Class |
| --- | --- |
| Declarative instance styling | `Tailwind`, `Slot`, `SlotMode` |
| Class syntax and safety | `Classes\ClassValidator`, `Classes\ClassNormalizer`, `Classes\ClassList` |
| Conflict-aware merging | `Classes\ClassMerger`, `Classes\UtilityGroups` |
| Non-color design tokens | `Tokens\DefaultTokens`, `Tokens\DesignToken`, `Tokens\TokenCollection`, `Tokens\TokenType` |
| Themes | `Themes\Theme`, `Themes\ThemeRegistry` |
| Presets | `Presets\DefaultPresets`, `Presets\PresetRegistry` |
| Slot declarations and overrides | `Slots\SlotDefinition`, `Slots\SlotRegistry` |
| Resolution (runtime contract) | `Slots\SlotResolver` implements `Gcego\Runtime\Contracts\StylingResolver` |
| Facade-free entry point | `ChromaManager` |
| Colors, appearance, accents, branding | gcego/swatches (delegated from `ChromaManager`) |
| Diagnostics | `Diagnostics\ChromaInspector`, `ChromaReport`, `ChromaDiagnostic` |

## Boundaries

- **No rendering:** styling never renders or parses components. gcego/runtime calls
  `StylingResolver::classes($component, $slot, $context)` through the `$styles`
  helper in Blade renderers, and `$context->theme` selects the theme.
- **Not a child:** `Tailwind` implements `ConfigurationNode`. It lives in
  `configurations()` and never appears in children, traversal or rendering.
- **No CSS generation:** Tailwind builds the CSS. The package ships tokens, a
  `@theme inline` mapping for non-color tokens and `@source` paths; colors and the
  dark variant come from gcego/swatches (imported by `gcego.css`).
- **No per-user state on the server:** services hold registrations only. The
  appearance choice lives in the browser (`localStorage`), and theme selection
  arrives per render through `RenderContext::$theme`.

## Caching

Layers 1–4 are static for a class, slot and theme. `SlotResolver` caches them
under `class|slot|theme`, and every registry has a `version()` that is part of
the key, so registering a preset, theme, slot or override invalidates the cache.
Layer 5 depends on the instance and is applied each time; it costs about 4 µs per
slot (see `benchmarks/run.php`).

## Merging

`ClassMerger` splits each class into variants, an important flag and a utility.
It then classifies the utility with an explicit table of Tailwind v4 utility
groups (`UtilityGroups`). Two classes conflict only when they have the same
variants (in any order), the same important flag and the same group.

- **Shorthands:** a shorthand also removes the classes it refines: `p-4` removes
  earlier `px-*`/`pt-*`, and `rounded` removes corners.
- **Placement:** replacements take the position of the class they replace.
- **Unknown utilities:** classes the table doesn't know are always kept, and
  arbitrary properties such as `[mask-type:x]` group by property.

This is a curated subset of Tailwind's utilities, not a re-implementation of
Tailwind. See the limits in [slots](slots.md#limits-of-merging).
