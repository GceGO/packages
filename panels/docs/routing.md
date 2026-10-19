# Routing

## Names and URIs

| Panel | Page | Route name | URI |
| --- | --- | --- | --- |
| `admin` (Path `admin`) | home | `gcego.admin.home` | `/admin` |
| `admin` | `reports` | `gcego.admin.reports` | `/admin/reports` |
| `admin` | module `academics`, subjects index | `gcego.admin.academics.subjects.index` | `/admin/subjects` |
| `school` (Tenant `required`) | subjects view | `gcego.school.academics.subjects.view` | `/school/{institution}/subjects/{record}` |
| `partners` (Domain `partners.example.com`, Path `''`) | `reports` | `gcego.partners.reports` | `partners.example.com/reports` |

Route names follow `<gcego-panels.routing.name_prefix><Panel::RouteName>.<page route>`. A page's route and path are gcego/pages', so `Page::Path`, `Where` constraints and `{record}` constraints keep working.

The home route is registered unless a page already sits at the panel root. It redirects to the dashboard page, or shows a welcome state when there is none.

## Middleware

```
[...gcego-panels.routing.middleware, auth[:guard], verified?, gcego.tenant:<mode>?, gcego.panel:<name>, ...Panel::Middleware, ...page middleware]
```

## Rules

| Rule | Behavior |
| --- | --- |
| Membership | A page is routed only in panels that include it (module or reference). With panels routing pages, pages outside every panel have no route. |
| Conflicts | Same domain and path, or the same route names: `InvalidPanelException`. gcego/pages reports URI and name conflicts with application routes. |
| Missing references | A module or page that is not registered: `InvalidPanelException` while routing. |
| Route cache | Panel routes are part of the route cache. After `route:cache`, routes come from the cache and nothing is registered at boot. Run `route:clear` after adding panels. |
| Same page, several panels | `PageRouteResolver::routeName()` prefers the route of the current request's panel (also during Livewire updates). |
| Tenant URLs | Inside a tenant panel, the middleware sets `URL::defaults([tenant parameter => current tenant])`. |

## Leaving routing to gcego/pages

With `gcego-panels.routing.register_page_routes` set to `false`:

- panels register no routes;
- gcego/pages routes pages as configured in `gcego-pages.routing`, with its own layout.

Use it to adopt panels gradually.
