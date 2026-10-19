# Changelog

## Unreleased

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
