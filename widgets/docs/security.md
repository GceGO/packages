# Security

| Threat | Defence |
| --- | --- |
| Forged widget, provider or chart configuration | Widgets are looked up by registry identity only (`widget:<name>`; classes and other kinds are 404). Providers, closures, aggregates and chart options are definitions. The client sends a range, dates and a page, all validated. |
| Unauthorized widgets | gcego/security requirements on the widget are checked on mount and every request (deny by default: a widget without a requirement is denied). |
| Unauthorized metrics | A child's requirements are checked per child, and unauthorized children are not rendered. Embedded widgets inherit the requirements of their layout or blueprint down to their path, on every render and every `WidgetHost` request. |
| Forged embedded widgets | `WidgetHost` re-resolves the owner from the registry and the widget at its locked path on every request; unknown owners, paths and non-widget paths are 404. |
| Bypassing data authorization | Aggregates, feeds and recent records run through gcego/data as the current user, so the model policy, tenant scopes and mandatory constraints apply. A denied data source hides the metric even when the widget permission passes. Blueprint sources check the blueprint's requirements. |
| Cross-tenant data | Tenant scopes on every query. Eager-loaded relations (feed actors) use the related model's scopes. Cache keys include the tenant. |
| Cross-user cache leakage | `user`-scoped keys by default. Authorization runs before any cache read. Only results are cached. |
| Unsafe chart configuration | Data is validated by `ChartDataNormalizer`. The browser receives an allowlisted JSON description (`JSON_HEX_*`, inside `<script type="application/json">`) that the JS module re-validates. Functions and callbacks exist only in the module. |
| XSS | Every label, value and item is escaped. Links (`Stat::Url`, feeds) must be http(s), mailto or root-relative. Icons come from the trusted set. |
| Error leakage | Provider exceptions are reported. Users see a generic error state. |
| Invalid state | Ranges and pages are validated (400, or a reset with a notice in Livewire). Custom ranges are bounded. |
| Serialization | The Livewire snapshot holds the identity, record key, range, dates and page only. |
