# Architecture

```
app/GCEGo/Widgets/*.php ─► gcego/registry (kind "widget")
        widget:total_students ─► Widget definition (immutable, query-free)

WidgetManager
├── resolve()      registered identities only (404 otherwise)
├── authorize()    gcego/security requirements of the widget (and of a child, by path)
├── state()        untrusted range / dates / page ─► WidgetState (Period)
├── context()      WidgetDataContext: widget, user, tenant, period, record, page, locale, zone
├── data()         WidgetDataResolver: Value | DataProvider | Aggregate | ActivitySource (+ opt-in cache)
└── render()       frame ─► Runtime ─► WidgetRenderer ─► widget view
                                       └─ Chart: ChartDataNormalizer ─► ChartAdapter ─► JSON ─► resources/js/charts
```

## Who owns what

| Concern | Owner |
| --- | --- |
| Registration and discovery | gcego/registry (`WidgetKind`) |
| Aggregation SQL, scopes, policies | gcego/data (`DataManager::aggregate()`, `query()`) |
| Widget and child requirements | gcego/security (`SecurityManager::definition()`) |
| Number, money and date formatting; icons; safe URLs | gcego/tables' shared `Support\Formats`, `Icons`, `SafeUrl` |
| Record summaries | gcego/infolists |
| Table widgets | gcego/tables |
| Definitions, data resolution, presentation, refresh, caching, chart adapter | gcego/widgets |

## Rendering modes

| Mode | When |
| --- | --- |
| Static Blade | `WidgetManager::render()` and `<x-gcego-widgets::widget>` for widgets without state |
| Livewire (`WidgetHost`) | widgets with a DateRange, Refresh or feed pages, or `Interactive` |
| Embedded | a widget inside a registered layout or blueprint (gcego/layouts): rendered as a child of "layout:…" or "blueprint:…", authorized through the owner (its requirements are inherited down to the widget's path); interactive widgets mount `WidgetHost` with the owner identity and the widget's locked path |

## Layout metadata

`Widget::layout()` (span, height, min width) is metadata for gcego/layouts:
a widget's Span becomes its grid column span when it has no `ColumnSpan`.
This package does not lay out dashboards.
