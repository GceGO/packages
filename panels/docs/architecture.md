# Architecture

```
app/GCEGo/Panels/*.php ──► gcego/registry (kind "panel")  ──► index metadata (scalars)
                                 │ derives navigation:panel-<name>
                                 ▼
   app booted ──► PanelRouteRegistrar ──► gcego/pages PageRouteRegistrar (per panel: prefix, names, domain, middleware, page filter)
                                 │
request ──► web ─► auth[:guard] ─► verified? ─► gcego.tenant ─► gcego.panel:<name> ─► panel middleware ─► PageController / PanelHomeController
                                                                   │ PanelAccess (requirements + AccessPolicy)
                                                                   ▼
                                                     PanelContextResolver (scoped) ──► PanelShellFactory ──► gcego-panels::document
```

| Piece | Responsibility | Lifetime |
| --- | --- | --- |
| `Panel` | the immutable definition | registry (lazy) |
| `PanelKind` | validation and index metadata | registered in `register()` |
| `PanelDefinitions` / `PanelDescriptor` | panels from the index, by name (validated) | singleton |
| `PanelMembership` | page identities of a panel (module page maps, references) | singleton |
| `PanelRouteRegistrar` / `PanelRoutes` | routes and URLs | singleton |
| `AuthorizePanel` / `PanelAccess` | the access decision, deny by default | per request |
| `PanelContextResolver` | the verified `PanelContext` | scoped (per request, job, Octane request) |
| `PanelNavigationContributor` | navigation structure (cached per process) | resolved once by gcego/navigation |
| `PanelSwitcher`, `UserMenu`, `PanelShellFactory` | what the current user sees | scoped |
| `PanelInspector` | diagnostics, no queries | singleton |

## What gcego/panels does not do

| Concern | Who does it |
| --- | --- |
| Sign-in, passwords, two-factor authentication | Laravel, gcego/sentinel |
| Page routing, rendering, records | gcego/pages |
| Resources and CRUD | gcego/modules |
| Authorization, policies, tenant scopes | gcego/security |
| Menus, the mobile drawer, breadcrumbs | gcego/navigation |
| Themes and class merging | gcego/chroma |
| Studio and notifications | future packages |

Other packages extend every panel through `Contracts\PanelExtension`:

- `routes(Router, PanelDescriptor)` registers routes inside the panel's group (prefix, domain, middleware, access checks, route name namespace);
- `topbar(PanelShell)` places markup in the top bar, before the user menu;
- `body(PanelShell)` places markup at the end of the document.

Register an extension with `PanelManager::extend()` or `gcego-panels.extensions`. Extension routes can answer with `PanelManager::respond($title, $content)` to render inside the panel shell. gcego/notifications adds its bell, toasts and inbox this way.

Studio can also use `UserMenuItem`, `Panel::Middleware`, the views (publishable) and the styling slots.

## Booting

Booting never queries and never loads a panel definition.

- **`register()`:**
  - binds services;
  - registers the `panel` kind;
  - adds `gcego-panels.discovery.paths` to the registry roots when missing;
  - just before gcego/pages reads its configuration, turns off pages' own routing and sets its layout to `gcego-panels::document`.
- **`boot()`:**
  - loads views and translations;
  - registers the `gcego.panel` middleware alias and Livewire persistent middleware;
  - defines the styling slots and icons;
  - adds the navigation contributor;
  - registers routes after the application has booted.
