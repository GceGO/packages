# gcego/panels

Multi-panel workspaces for GCEGO Admin Studio. A panel is one administrative area. It gets its own:

- URL prefix, route names and optional domain;
- middleware and authentication guard;
- access rules and tenancy;
- modules, pages and dashboard;
- navigation, branding, theme and responsive shell.

```php
// app/GCEGo/Panels/admin.php
return new Panel(
    new Property(Panel::Name, 'admin'),
    new Property(Panel::Title, 'GCEGO Administration'),
    new Property(Panel::Dashboard, 'overview'),
    new Ability('access-admin'),                   // who may enter

    new ModuleReference('academics'),
    new PageReference('reports'),
);
```

This gives you:

- `/admin`, which redirects to the dashboard;
- `/admin/overview`, `/admin/reports`, `/admin/subjects`, `/admin/subjects/create`…;
- route names `gcego.admin.overview`, `gcego.admin.academics.subjects.index`…;
- a sidebar shell whose navigation lists the dashboard, the reports page and the module's resources.

Only users the `access-admin` gate allows get in, on every request and every Livewire update.

gcego/panels orchestrates the other packages; it adds no engine of its own:

| Concern | Package |
| --- | --- |
| Routing and page rendering | gcego/pages |
| Modules | gcego/modules |
| Authentication | Laravel or gcego/sentinel |
| Authorization and tenancy | gcego/security |
| Menus | gcego/navigation |
| Themes and classes | gcego/chroma |

## Contents

- [Installation](#installation)
- [Examples](#examples)
- [Defining panels](#defining-panels)
- [Routes](#routes)
- [Access, authentication and tenancy](#access-authentication-and-tenancy)
- [Navigation](#navigation)
- [Shells, branding and themes](#shells-branding-and-themes)
- [Switching panels and the user menu](#switching-panels-and-the-user-menu)
- [Livewire](#livewire)
- [Context, manager and diagnostics](#context-manager-and-diagnostics)
- [Security](#security)
- [Testing](#testing)

## Installation

```bash
composer require gcego/panels
php artisan vendor:publish --tag=gcego-panels-config   # optional
php artisan vendor:publish --tag=gcego-panels-views    # optional
```

The package discovers panels from `app/GCEGo/Panels`: each PHP file there returns one `Panel`.

While panels route pages (`gcego-panels.routing.register_page_routes`, the default), two things change in gcego/pages:

- **Routes:** gcego/pages registers no routes of its own. A page is reachable only through the panels that include it.
- **Layout:** pages render inside the panel shell, unless your application set its own `gcego-pages.rendering.layout`.

The shell's Tailwind classes are already in gcego/chroma's `gcego.css` sources.

## Examples

A super-admin panel, admitted by an access policy:

<!-- example:super-admin -->
```php
return new Panel(
    new Property(Panel::Name, 'super-admin'),
    new Property(Panel::Title, 'Platform'),
    new Property(Panel::Path, 'platform'),
    new Property(Panel::AccessPolicy, \App\Panels\SuperAdminAccess::class),
    new Property(Panel::Layout, 'topbar'),
    new Branding(
        new Property(Branding::Name, 'GCEGO Platform'),
        new Property(Branding::Logo, '/images/gcego-logo.svg'),
        new Property(Branding::LogoDark, '/images/gcego-logo-dark.svg'),
    ),
);
```

A school workspace, one per institution, at `/school/{institution}/…`:

<!-- example:school -->
```php
return new Panel(
    new Property(Panel::Name, 'school'),
    new Property(Panel::Title, 'School Portal'),
    new Property(Panel::Tenant, 'required'),
    new TenantMember('teacher', 'admin'),

    new ModuleReference('academics'),
    new UserMenuItem('Account security', 'profile'),
);
```

A staff room on its own domain, with its own guard and verified emails:

<!-- example:staff -->
```php
return new Panel(
    new Property(Panel::Name, 'staff'),
    new Property(Panel::Domain, 'staff.example.com'),
    new Property(Panel::Path, ''),
    new Property(Panel::Guard, 'web'),
    new Property(Panel::Verified, true),
    new Property(Panel::Middleware, ['throttle:120,1']),
    new Authenticated,

    new PageReference('reports'),
    new PageReference('audit', new Property(PageReference::Navigation, false)),
);
```

## Defining panels

`Panel` is an immutable gcego/morfaw definition. Its registry kind is `panel` and its identity is `panel:<name>`. Constructing a panel never queries, registers routes or authenticates anyone.

| Property | Default | Meaning |
| --- | --- | --- |
| `Name` | (required) | lowercase words with `-`: `admin`, `school-admin` |
| `Title` | from the name | shown in the shell, the switcher and the page title |
| `Description` | none | shown in the switcher and the footer |
| `Path` | the name | URL prefix (`admin`, `school/manage`, `''` for the root); no parameters |
| `Domain` | any host | `admin.example.com` or `{institution}.example.com` |
| `RouteName` | the name | route names are `<gcego-panels.routing.name_prefix><RouteName>.<page route>` |
| `Guard` | `gcego-panels.security.guard`, else the default | the authentication guard |
| `Middleware` | `[]` | extra middleware, after the panel checks |
| `AccessPolicy` | none | a `Contracts\PanelAccessPolicy` class |
| `Tenant` | none | `optional` or `required` (gcego/security tenancy) |
| `Verified` | `false` | adds Laravel's `verified` middleware |
| `Dashboard` | none | the page the panel home redirects to (added to the panel) |
| `Navigation` | `panel-<name>` | use a registered navigation instead of the derived one |
| `Layout` | `gcego-panels.appearance.default_layout` | `sidebar`, `topbar` or `compact` |
| `Theme` | `gcego-panels.appearance.default_theme` | a gcego/chroma theme |
| `Order`, `Hidden` | `0`, `false` | position in, or absence from, the panel switcher |
| `Vite` | `gcego-panels.assets.vite`, else `gcego-pages.rendering.vite` | Vite entries of the panel's documents |

The panel's children are:

- `ModuleReference('academics')`: every page of a gcego/modules module;
- `PageReference('reports')`: one gcego/pages page, listed in the navigation unless `PageReference::Navigation` is `false`;
- one `Branding`;
- `UserMenuItem`s.

Requirements (gcego/security nodes such as `Ability`, `TenantMember` and `Authenticated`) decide who may enter.

## Routes

The package registers panel routes once the application has booted. They come from the registry index alone, so no panel definition is loaded, and nothing is registered when routes are cached. gcego/pages' `PageRouteRegistrar` builds them.

| Route | Name | URI |
| --- | --- | --- |
| Home | `gcego.admin.home` | `/admin` (redirects to the dashboard, or shows a welcome state) |
| Page | `gcego.admin.reports` | `/admin/reports` |
| Module page | `gcego.admin.academics.subjects.index` | `/admin/subjects` |
| Tenant panel | `gcego.school.academics.subjects.view` | `/school/{institution}/subjects/{record}` |

Every panel route runs this middleware, in order:

1. `gcego-panels.routing.middleware` (`web`);
2. `auth` or `auth:<guard>`;
3. `verified`, if the panel requires it;
4. `gcego.tenant:<optional|required>`;
5. `gcego.panel:<name>`;
6. the panel's own middleware;
7. each page's own middleware.

Two panels on the same path and domain, or with the same route names, are a configuration error. So are references to modules or pages that are not registered.

When one page is in several panels, gcego/pages' `PageRouteResolver` keeps links and redirects in the current panel.

## Access, authentication and tenancy

Panels are deny-by-default. A panel with no requirement and no `AccessPolicy` admits nobody, and `PanelInspector` reports it as `access.undeclared`. A user enters a panel only if:

1. Laravel authenticated them with the panel's guard;
2. every requirement allows, for the current user and tenant;
3. the `AccessPolicy`, if any, allows.

```php
final class SuperAdminAccess implements PanelAccessPolicy
{
    public function allows(Authenticatable $user, PanelContext $context): bool
    {
        return Gate::forUser($user)->allows('access-super-admin');
    }
}
```

An exception while deciding denies access. Access to one panel never implies access to another. Page requirements, model policies, record authorization and tenant scopes still apply to every page inside a panel.

Tenancy uses gcego/security:

- `Tenant: required` adds `{institution}` (the configured tenant parameter) to the panel prefix, unless the domain carries it. Members of other tenants get a 404.
- `Tenant: optional` uses the tenant selected in the session, if any.

Inside a tenant panel, route URLs default to the current tenant.

Signing in is Laravel's or gcego/sentinel's job. Guests are sent to the `login` route by Laravel's `auth` middleware. The user menu signs out through the `logout` route (POST, with the CSRF token).

## Navigation

Each panel derives a gcego/navigation navigation, `navigation:panel-<name>`. It lists:

- the dashboard;
- the referenced pages, grouped by their `NavigationGroup`;
- a group per module, holding its resources' list pages and its own pages.

Every link points to the panel's routes and carries the page's own requirements, so each user sees exactly the pages they may open. Pages that need a record or parameters are left out. A panel that names a registered navigation (`Panel::Navigation`) gets these items added to it.

## Shells, branding and themes

| Layout | Desktop | Mobile |
| --- | --- | --- |
| `sidebar` | a sticky sidebar (collapsible, remembered in the browser) with branding, navigation and the switcher; a top bar with the user menu | a top bar and gcego/navigation's modal drawer |
| `topbar` | branding, top navigation, switcher and user menu in one bar | the same drawer |
| `compact` | a narrower sidebar | the same drawer |

Every shell has:

- a skip link;
- landmarks: `aside` with a label, `header`, `main` and `footer`;
- one `main` that takes focus;
- native disclosure menus that work without JavaScript, which Alpine closes on Escape or an outside click;
- the drawer's focus trap and Escape handling;
- visible focus rings and reduced-motion support.

`Branding` holds a name, logos (light, dark, compact), a favicon, a tagline and a home URL. URLs must be root-relative or `https`. Text is always escaped.

To restyle a slot, give the panel classes with gcego/chroma:

```php
new Tailwind(new Slot(Panel::SidebarSlot, ['lg:w-80']), new Slot(Tailwind::Content, ['bg-gcego-surface']))
```

The slots are:

- `root`, `content` and `footer`;
- `sidebar`, `sidebar_header`, `sidebar_body` and `sidebar_footer`;
- `topbar`, `main` and `drawer`;
- `branding`, `user_menu` and `switcher`.

A panel's `Theme` sets the document's gcego/chroma theme.

## Switching panels and the user menu

The switcher lists the panels the user may enter: not hidden, the same guard, admitted for the same user and tenant. A listed panel still checks access when it is opened.

The user menu shows, in order:

1. the panel's `UserMenuItem`s (each needs an existing route; requirements are optional);
2. `gcego-panels.user_menu` links;
3. "Sign out".

## Livewire

Panel pages are gcego/pages pages, so the content is one Livewire `PageHost`.

`gcego.panel` is also Livewire persistent middleware. On every update, Livewire re-runs authentication, tenancy and the panel check for the page the component was mounted on. That page comes from Livewire's checksummed snapshot, never from a value the browser can choose.

The panel context is server-side, request-scoped state. It is never put into a component or the page.

## Context, manager and diagnostics

```php
app(PanelManager::class)->current();                      // PanelContext: panel, user, guard, tenant (or null)
app(PanelManager::class)->url('school', ['institution' => 2]);   // "/school/2"
app(PanelManager::class)->pageUrl('admin', 'reports');   // "/admin/reports"
app(PanelManager::class)->switcher();                     // PanelLink[]
app(PanelManager::class)->inspect('admin');               // PanelReport
```

`PanelReport` gives the panel's path, route namespace, guard, middleware, pages and routes. It also carries diagnostics:

- `access.undeclared`;
- `module.missing` and `page.missing`;
- `guard.unknown`;
- `navigation.missing`;
- `theme.unknown`;
- `routes.missing`;
- `tenancy.disabled`.

## Security

- **Deny by default:** no access rules means no access, and no configuration value turns access checks off.
- **Every request is checked:** each panel request and each Livewire update checks authentication, tenancy and panel access. Page, record and tenant authorization still apply inside the panel.
- **Fixed panel names:** panel names come from route definitions, never from request input. Unknown names are 404s.
- **No privilege escalation:** access to one panel grants nothing in another. A page is routed only in the panels that include it.
- **Safe branding:** branding URLs are validated and all text is escaped. The panel context and the user are never serialized to the browser.

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan (max)
composer format     # Pint
```

See [docs/](docs) for routing, access, shells and the API reference.
