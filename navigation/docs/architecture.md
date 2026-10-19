# Architecture

```
new Navigation('admin', …)       immutable definition: no routes, authorization or queries at construction
   │  gcego/registry (kind "navigation")  ·  NavigationContributor classes (lazy)
   ▼
NavigationTree (NavigationTreeBuilder)       structure only: validated, ordered, contributions merged
   │  cached per definition and process (WeakMap); shared by every user
   ▼
NavigationResolver (request-scoped)          per request, user and tenant:
   ├─ VisibilityResolver   requirement chains → gcego/security engine (memoized per chain and context)
   ├─ RouteResolver        named routes → URLs (memoized), ":tenant" parameters
   ├─ ActiveStateResolver  one best-matching item; groups holding it expand
   └─ BadgeResolver        visible items only; optional per-user, per-tenant cache
   ▼
ResolvedNavigation ──► NavigationRenderer (gcego/runtime) ──► sidebar | topbar | mobile | breadcrumbs views
                   └─► BreadcrumbResolver (lazy) ──► BreadcrumbCollection
```

## Caching boundaries

| Data | Cached | Where |
| --- | --- | --- |
| Definitions | yes | gcego/registry (manifest cache) |
| Structure (`NavigationTree`) | yes, process-wide | `NavigationManager`, per definition object (no user data) |
| Visibility decisions | per security fingerprint (user, guard, tenant, membership) | `VisibilityResolver`, request-scoped |
| Route URLs | per route and parameters | `RouteResolver`, request-scoped |
| Resolved navigation | per request URL and security fingerprint | `NavigationResolver`, request-scoped |
| Badges | only with `Item::BadgeTtl` | cache store, keyed by navigation, item, version and a hash of the security fingerprint; `forgetBadges()` invalidates |
| Breadcrumbs | never | computed on request |

Nothing user-, tenant- or page-specific is ever cached under a shared
key. Route and configuration caching work as usual, because definitions
reference route names and URLs are generated per request.

## Contributions

Modules, pages and panels add nodes through `Contracts\NavigationContributor`:
- a target navigation name;
- static nodes.

Register the class with `NavigationManager::contribute()` or in
`gcego-navigation.contributors`.

A contributor that serves several navigations (gcego/panels builds one per
panel) implements `Contracts\MultipleNavigationsContributor`: `contributesTo()`
picks the navigations and `nodesFor()` returns each one's nodes.

| Rule | Details |
| --- | --- |
| Loading | Contributors are resolved the first time their navigation is built in a process; nothing is scanned per request. |
| Groups | A contributed `Group` whose Name matches a root group of the navigation is merged into it. |
| Other nodes | Added at the root. |
| Duplicates | Duplicate names fail (`DuplicateNavigationItemException`). |
| Ordering | By `Order`, then contributor registration order, then declaration order. Deterministic across requests and deployments. |

## Rendering entry points

| Entry point | Notes |
| --- | --- |
| `NavigationManager::render($identity, $display)` | Registered navigations only (unknown identities are 404). |
| `<x-gcego-navigation::navigation name="…" display="…" />`, `<x-gcego-navigation::breadcrumbs navigation="…" />` | |
| A `Navigation` inside a blueprint or a layout (for example a layouts `Aside`) | Rendered by the runtime with its `Display`. |
| `NavigationManager::renderNavigation($definition)` | Trusted server code. |

A navigation the user can see nothing of renders nothing (no empty
`<nav>`).
