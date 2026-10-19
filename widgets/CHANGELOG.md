# Changelog

## Unreleased

- **Changed:** pie and doughnut slices use gcego/swatches' categorical chart colors (`--gcego-chart-1 … 8`) instead of cycling status tones; `neutral` reads `--gcego-chart-neutral`.

- **Added:** `ActivitySource` takes a typed Luminaries icon.
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## Unreleased

- Widgets embedded in a registered layout or blueprint (a child of "layout:…" or "blueprint:…" in a render) render without a registry entry of their own: they inherit the owner's requirements down to their path, and interactive ones mount `WidgetHost` with the owner identity and a locked `path`. New `WidgetManager::locate($owner, $path)`; `authorize()` and `allows()` accept any definition; `forget()` accepts an owner identity; `renderWidget()` takes the owner and path; `WidgetRenderFrame` has `base` and `path()`.

## 0.1.0 - 2026-10-17

First release.

- `Widget` base; the "widget" kind in gcego/registry (`widget:<name>`, discovered from `app/GCEGo/Widgets`); `WidgetManager`.
- Statistics: `Stat` (number, decimal, percent, currency, compact, duration and ratio formats), `Stats` groups with shared providers, and `Trend` with explicit meaning (positive, negative or neutral).
- Charts: `Chart` and `LineChart`, `BarChart`, `AreaChart`, `PieChart`, `DoughnutChart`; `ChartDataNormalizer`; the `ChartJsAdapter`; the lazy Chart.js browser module; text summaries and data tables.
- `Progress` and `Goal` (at_least and at_most).
- `ActivityFeed` with `ActivitySource`, `RecentRecords`, `RecordSummary` (gcego/infolists) and `TableWidget` (gcego/tables).
- Data: `Value`, `DataProvider` (`WidgetDataProvider`), `Aggregate` (gcego/data aggregation), `DateRange` and `Period`, `WidgetDataContext`, `WidgetDataResult`, `WidgetDataResolver`.
- `Refresh` (polling with a minimum interval, events) and opt-in `Cache` (user- or tenant-scoped keys, invalidation).
- `WidgetRenderer`; Livewire `WidgetHost` (`gcego-widget`); the `<x-gcego-widgets::widget>` component.
- `WidgetInspector` diagnostics.
