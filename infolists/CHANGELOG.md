# Changelog

## Unreleased

- **Added:** Filament-style authoring on gcego/morfaw. `Infolist::make()`, `Text::make()`, `Section::make()` and every other entry and layout have `make()`.
- **Added:** per-record closures. Entry properties (`label`, `description`, `tooltip`, `placeholder`, a badge's variants, ...) accept closures injected with `$state`, `$record` (also by model type), `$root` (the infolist's record, also inside repeatable items), `$entry` and `$user`. Each render resolves a copy of the entry (`Entry::forRecord()`), and results are type-checked and escaped. Masked entries only resolve their label, never value-derived properties.
- **Added:** `state()` and `formatStateUsing()` with named and typed injection (`resolveUsing()` and `formatUsing()` keep their positional signatures), and `visible()`/`hidden()` closures on entries and layouts (`VisibleWhen::computed()`, a new `Building\LayoutBuilder`). Visibility still never grants access.
- **Added:** infolist extensions. `Components::extend('{blueprint}.infolist', fn (SchemaPatch $patch) => ...)` patches a blueprint's infolist wherever it is resolved (`InfolistManager::extended()`).

- **Added:** icon maps (`Badge::Icons`, `Icon::Icons`) and icon values of `Icon` entries take typed Luminaries icons.

## 0.1.0 - 2026-10-17

First release.

- `Infolist` blueprint part with entries, layouts and gcego/actions record actions; WithTrashed, Columns and EmptyHeading.
- Entries:
  - `Text` (multiline, truncation, monospace, Markdown and HTML through the sanitizer);
  - `Number`, and `Money` (ISO 4217, XAF default, minor units);
  - `Boolean`, `Badge`, `Icon`;
  - `Image` (URLs, storage paths, temporary URLs for private files);
  - `Date`, `DateTime`, `Link` (routes, mailto, new tab, copy);
  - `KeyValue` (allow-listed keys, sensitive keys refused), `ListEntry`, `RepeatableEntry`.
- Configuration: `Format`, `Resolve`, `Access` (gcego/security FieldRule, with mask), `VisibleWhen`; layouts accept gcego/security requirements.
- Layouts: `Section` (icon, collapsible), `Grid`, `Tabs`/`Tab` (WAI-ARIA), `Split`.
- Records through `SecurityManager::record()` on the blueprint Source:
  - scopes, tenancy, policies, any key type;
  - relations eager-loaded once, each under the related model's data scopes.
- Rendering: `InfolistRenderer`, `EntryRenderer`, `LayoutRenderer` with gcego/runtime; `<dl>` markup; styling slots; the anonymous `<x-gcego-infolists::infolist>` component.
- Livewire `InfolistHost` (`gcego-infolist`) with record actions and an unavailable state.
- `InfolistInspector` diagnostics.
