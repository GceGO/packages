# Architecture

gcego/pages turns page definitions into routed, authorized, rendered pages by composing the other GCEGO packages. It owns the page; everything inside the page belongs to the package that already draws it.

```
HTTP GET /admin/subjects/7/edit
  └─ Laravel route (registered from the registry index)  ── PageController
       └─ PageManager::open()  ── PageLifecycle (request-scoped)
            ├─ PageDefinitions        gcego/registry: "page:subjects-edit"
            ├─ PageAuthorizer         gcego/security: page requirements, scoped record, record ability
            ├─ PageRecordResolver     {record} shape, record label
            ├─ PageBreadcrumbs        gcego/navigation Breadcrumbs
            └─ PageActionResolver     header actions and links
       └─ Livewire PageHost (locked identity, record key, parameters, tab)
            └─ PageManager::renderContext()  ── gcego/runtime ── PageRenderer
                 ├─ header: breadcrumbs, h1, description, PageLinks, ActionHost (gcego/actions)
                 └─ body: FormHost (gcego/forms), TableHost (gcego/tables), InfolistHost (gcego/infolists),
                          layouts (gcego/layouts), widgets (gcego/widgets), Content
```

## Pages are blueprints

`Page` extends gcego/blueprints' `Blueprint`, and the registry knows it as the `page` kind. This is what lets the existing hosts work without changes: a `TableHost`, `FormHost`, `InfolistHost`, `ActionHost` or embedded widget is mounted with the page's identity (`page:subjects-edit`), finds its part in the registered page, checks the requirement chain from the page down to its own path, and queries through the page's `Source`.

gcego/registry was taught one thing for this: when two kinds match an object (a Page is also a Blueprint), the most specific contract wins, so a runtime-registered page is a `page`, not ambiguous.

## Process-wide and request-wide state

| Lifetime | Services | Holds |
| --- | --- | --- |
| Singleton | `PageManager`, `PageDefinitions`, `PageRouteResolver`, `PageRouteRegistrar`, `PageHooks`, `PageRenderContext`, `PageInspector`, renderers | definitions, the route map, hook class names: nothing user-specific |
| Scoped (request) | `PageLifecycle`, `PageAuthorizer`, `PageRecordResolver`, `PageBreadcrumbs`, `PageActionResolver` | open pages, decisions, records |

Renderers are shared by gcego/runtime, so they resolve request-scoped services on every render. Memoized decisions and open pages are keyed by the request object and the security fingerprint (user, guard, tenant), so a worker that keeps scoped services between requests never reuses them for someone else.

## What pages do not do

Pages never query records except through gcego/data (via gcego/security's `RecordAuthorizer`), never execute actions (gcego/actions does, in its own host), never validate input (gcego/forms), never lay out content (gcego/layouts) and never build navigation trees (gcego/navigation).
