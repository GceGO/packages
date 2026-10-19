# Changelog

## Unreleased

- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## 0.1.0 - 2026-10-17

First release.

- `Layout` (registered as "layout:<name>", discovered from `app/GCEGo/Layouts`), the `LayoutComponent` base (a blueprint part container), `LayoutManager` and the `<x-gcego-layouts::layout>` component.
- `Container`, `Grid` (`Columns`, auto columns, auto-fit, dense), `Row`, `Stack`.
- `Section` and `Card` with `Actions` and `Footer` regions, icons, empty text and collapsible sections.
- `Split` and `Sidebar` with `Main` and `Aside` regions.
- `Tabs` and `Tab` (WAI-ARIA, Alpine.js), `Accordion` and `AccordionItem`, `Collapsible` (native disclosures).
- `Divider`, `Spacer`, `ScrollArea`, `Sticky`, `EmptyState`, `Loading`.
- Responsive engine: `Breakpoint`, `ResponsiveValue`, `ColumnSpan` and `Visibility` (placement nodes every component accepts), `Gap`, `Align`; bounded literal class maps in `LayoutPresets`.
- `LayoutRenderer`, one runtime renderer for every layout component, with per-component styling slots.
- `LayoutInspector` diagnostics.
