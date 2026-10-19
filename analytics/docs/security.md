# Security, tenancy and privacy

| Threat | Mitigation |
| --- | --- |
| Unauthorized metrics, reports or dashboards | Deny by default: requirements on every dataset, report and dashboard (none means denied). Evaluated as the viewer in the tenant; errors deny. |
| Rows the viewer may not see | Every query starts from gcego/data's scoped builder: model policy `viewAny` (no policy denies), global scopes, soft deletes, tenant scope (fails closed without a tenant). Then the dataset's `AnalyticsScope`. Authorization happens before aggregation, never by filtering results. |
| Forged tenant | The context's tenant is verified (membership) when gcego/security switches to it; a non-member is refused. |
| Cross-tenant leakage through aggregates, labels, caches, exports or schedules | Tenant scopes in queries and label lookups; cache keys include the user and tenant; exports and schedules store their tenant and re-check it. |
| Sensitive fields | Not in the viewer's schema; refused as measures, filters and dimensions; report columns hidden. |
| Restricted dimensions (cross-institution) | Requirements on the dimension. |
| SQL injection | No browser value becomes SQL: columns come from definitions (identifier pattern, grammar-wrapped), values are bound, operators are an enum. Hostile names are refused by the planner. |
| Unbounded or expensive queries | Period, point, group, row, page and export limits; optional statement timeout. |
| Arbitrary code | Formulas are parsed into a fixed grammar; scopes, providers and label resolvers are classes named by definitions only. |
| Small groups identifying students | `minimumGroupSize` hides groups (and totals) of 1…k−1 rows; counts are hidden too. |
| CSV injection, predictable downloads, stale access | Formula prefixing; unguessable private paths behind signed, expiring, re-authorized links; schedules re-check owners and recipients every run. |
| Leaky logs and events | Events carry identifiers and counts only; no data, filter values or recipients. |
| Tampered Livewire state | Definition names and links are locked; state is validated on every request. |

## Privacy guidance

Data may concern minors.

- **Aggregate first:** prefer aggregated datasets, and set `minimumGroupSize` (5 or more) on datasets about students.
- **Narrow rows:** use an `AnalyticsScope` so students see only their own data.
- **Keep identifiers out:** never declare identifying columns as dimensions. Mark contact data `sensitive: true` with its own requirement.
- **Retention:** keep exports short-lived (`exports.ttl_hours`). Failed and expired export records are deleted after 30 days.
