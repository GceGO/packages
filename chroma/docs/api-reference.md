# API reference

All classes live under `Gcego\Chroma`.

## `ChromaManager` (final readonly, singleton)

| Method | Notes |
| --- | --- |
| `classes(ComponentInterface, string $slot, ?string $theme = null): string` | effective classes |
| `classList(…): ClassList` | the same, as a list |
| `preset(string $id, ?string $theme = null): ClassList` | preset with theme overrides |
| `merge(string\|array\|ClassList ...$lists): string` | conflict-aware merge |
| `defineSlots(string $component, list<SlotDefinition>, bool $inherit = true, bool $override = false): void` | |
| `override(string $component, Slot ...$slots): void` | application layer |
| `definePreset(string $id, string\|array\|ClassList, bool $override = false): void` | |
| `registerTheme(Theme, bool $override = false): void`, `theme(?string): Theme` | |
| `defaultTheme(): string`, `swatches(): SwatchesManager` | the color system |
| `rootAttributes(?string $theme = null, ?Brand $brand = null): HtmlString` | for `<html>` (delegates to gcego/swatches) |
| `appearance(): AppearanceMode`, `allowsSwitching(): bool`, `storageKey(): string`, `accents(): array` | delegate to gcego/swatches |
| `appearanceScript()`, `appearanceTag(?string $nonce)`, `accentTag(?string $theme, ?string $nonce)`, `brandTag(?Brand, ?string $theme, ?string $nonce)` | delegate to gcego/swatches |
| `themeTag(?string $theme, ?string $nonce): HtmlString` | Swatches theme CSS plus Chroma's non-color overrides |

## Declarative API

| Class | Members |
| --- | --- |
| `Tailwind` (readonly, `ConfigurationNode`) | `__construct(string\|array\|Slot ...$items)`, public `slots`, `slotsFor()`, `has()`, `slotNames()`, `apply(string, ClassList)`, `normalize()`; constants `Wrapper`, `Header`, `Title`, `Description`, `Body`, `Content`, `Footer`, `Label`, `Icon`, `Actions` |
| `Slot` (readonly) | `__construct(string $name, string\|array\|ClassList $classes = [], SlotMode $mode = Merge)`, `static replace()`, `static remove()`, `applyTo(ClassList)`, `toArray()` |
| `SlotMode` | `Merge`, `Replace`, `Remove` |

## Classes

| Class | Members |
| --- | --- |
| `Classes\ClassList` (readonly, Countable, IteratorAggregate, Stringable) | `static from()`, `static empty()`, `merge()`, `replace()`, `without()`, `contains()`, `all()`, `isEmpty()`, `toString()` |
| `Classes\ClassMerger` | `merge(list $base, list $overrides): list`, `conflicts(string, string): bool` |
| `Classes\ClassNormalizer` | `normalize(string\|iterable): list<string>`, `split(string): list<string>` |
| `Classes\ClassValidator` | `validate(string): void` |
| `Classes\UtilityGroups` (readonly) | `__construct(?list $colorTokens = null)`, `classify(string $utility): ?array{group, refines}` |

## Tokens, themes, presets, slots

| Class | Members |
| --- | --- |
| `Tokens\DefaultTokens` | `Definitions` (non-color), `names()`, `colorNames()` (gcego/swatches catalog), `type()` |
| `Tokens\DesignToken` | `name`, `value`, `property()`, `declaration()` |
| `Tokens\TokenCollection` | `static light()`, `static dark()`, `get()`, `has()`, `with()`, `toArray()`, `declarations()` |
| `Tokens\TokenType` | `Length`, `FontFamily`, `Shadow`, `Duration`; `accepts(string)` |
| `Themes\Theme` (readonly) | `__construct(name, tokens = [], dark = [], presets = [], ?label)`, `presetOverrides()`, `css()`, `static fromConfig()` |
| `Themes\ThemeRegistry` | `__construct(?Swatches ThemeRegistry)`, `register()`, `has()`, `get()`, `names()`, `version()`; knows every Swatches theme |
| `Presets\PresetRegistry` | `register()`, `has()`, `get()`, `ids()`, `all()`, `version()`, `static withDefaults()` |
| `Slots\SlotDefinition` | `name`, `preset`, `classes`, `description` |
| `Slots\SlotRegistry` | `register()`, `override()`, `for()`, `overridesFor()`, `registrations()`, `version()` |
| `Slots\SlotResolver` (`Gcego\Runtime\Contracts\StylingResolver`) | `classes(component, slot, RenderContext)`, `resolve(component, slot, ?theme): ClassList` |

## Diagnostics

`Diagnostics\ChromaInspector::inspect(?ComponentInterface $target = null, ?string $theme = null): ChromaReport`
never throws. Its checks are:

- `config`, `presets`, `slots` and `theme`;
- `contrast` (per theme and mode);
- `css`;
- for a target: `target.slots`, `target.unknown-slot`, `target.resolve` and
  `target`.

`ChromaReport` has public `diagnostics` and `resolved` (the path, class and
effective classes per slot), plus `check()`, `isHealthy()` and `toArray()`.

## Blade components

| Component | Props |
| --- | --- |
| `<x-gcego-chroma::appearance />` | `nonce` |
| `<x-gcego-chroma::theme />` | `name`, `nonce` |

## Configuration (`config/gcego-chroma.php`)

| Key | Default |
| --- | --- |
| `theme` | `env('GCEGO_STYLING_THEME')` (null follows `gcego-swatches.theme`) |
| `themes` | `[]` (non-color tokens and presets per theme) |

Appearance, accents and colors are configured in `config/gcego-swatches.php`.
| `overrides` | `[]` |
| `strict` | `true` |

## Exceptions (all implement `Exceptions\ChromaException`)

`InvalidClassException`, `InvalidSlotException`, `InvalidThemeException`,
`InvalidTokenException` and `StyleConflictException`.

## Service provider

`ChromaServiceProvider` implements `Gcego\Core\Contracts\PackageInterface`
(`gcego/chroma`, requiring gcego/core and gcego/runtime):

- capabilities `styling` and `styling.tailwind`;
- the view namespace `gcego-chroma` and the publish tag `gcego-chroma-config`;
- singletons for every service above;
- an `extend()` on `StylingResolver` that replaces only the runtime's `NullStylingResolver`.
