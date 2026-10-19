# gcego/navigation

Declarative navigation for GCEGO Admin Studio:
- **Nodes:** items, groups, dropdowns, headings, dividers and external links.
- **Resolution:** URLs from named routes, segment-aware active states,
  breadcrumbs, and lazy, tenant-scoped badges.
- **Visibility:** permission- and tenant-aware, through gcego/security.
- **Rendering:** sidebar, top, mobile-drawer and breadcrumb renderers.

You write the definition; the package handles markup, route matching,
permission checks, keyboard behavior and styling.

```php
// app/GCEGo/Navigation/Admin.php — discovered as "navigation:admin"
return new Navigation('admin', new Property(Navigation::Home, 'dashboard'),
    new Item(new Property(Item::Name, 'dashboard'), new Property(Item::Label, 'Dashboard'), new Property(Item::Route, 'admin.dashboard'), new Property(Item::Icon, 'home')),
    new Group('Academic Management',
        new Item(new Property(Item::Name, 'students'), new Property(Item::Label, 'Students'), new Property(Item::Route, 'admin.students.index'), new Property(Item::Active, 'admin.students.*'), new Property(Item::Icon, 'users')),
        new Item(new Property(Item::Name, 'subjects'), new Property(Item::Label, 'Subjects'), new Property(Item::Route, 'admin.subjects.index'), new Property(Item::Active, 'admin.subjects.*'), new Property(Item::Breadcrumbs, SubjectBreadcrumbs::class)),
    ),
    new Group('Administration', new TenantMember,
        new Item(new Property(Item::Name, 'institutions'), new Property(Item::Label, 'Institutions'), new Property(Item::Route, 'admin.institutions.index'), new Property(Item::Permission, 'institution.members.manage')),
        new Item(new Property(Item::Name, 'requests'), new Property(Item::Label, 'Requests'), new Property(Item::Route, 'admin.requests.index'), new Property(Item::Badge, PendingRequestsBadge::class), new Property(Item::BadgeLabel, 'pending requests')),
    ),
    new ExternalLink('Documentation', 'https://docs.example.com'),
);
```

```blade
<x-gcego-navigation::navigation name="admin" />                   {{-- sidebar --}}
<x-gcego-navigation::navigation name="admin" display="topbar" />
<x-gcego-navigation::navigation name="admin" display="mobile" />
<x-gcego-navigation::breadcrumbs navigation="admin" />
```

## Examples

```php
new Navigation('admin', new Item('Dashboard', '/admin'), new Item('Students', '/admin/students'))
new Navigation('admin', new Group('Academics', new Item('Subjects', '/admin/subjects'), new Item('Topics', '/admin/topics')))
new Item('Students', 'admin.students.index')     // a route name (no leading "/" or scheme)
new Item(new Property(Item::Name, 'students'), new Property(Item::Label, 'Students'), new Property(Item::Route, 'admin.students.index'), new Property(Item::Permission, 'students.view'))
new Item(new Property(Item::Label, 'Members'), new Property(Item::Route, 'admin.institutions.members'), new Property(Item::Parameters, ['institution' => ':tenant']))
new Dropdown('Reports', new Property(Dropdown::Align, 'end'), new Item(…), new Item(…))
new Heading('Planning')   new Divider   new ExternalLink('Help', 'mailto:help@example.com')

new Navigation('admin', new Item('Dashboard', '/admin'), new Tailwind(
    new Slot(Tailwind::Wrapper, ['flex', 'flex-col', 'gap-1', 'p-3']),
    new Slot(Navigation::ItemSlot, ['flex', 'items-center', 'gap-3', 'rounded-xl', 'px-3', 'py-2.5', 'text-sm', 'font-medium']),
))
```

The item slot constant is `Navigation::ItemSlot`, not `Tailwind::Item`:
gcego/chroma's `Tailwind` defines only generic slots, so the
navigation-specific ones live on `Navigation`.

## Installation

The package is a path repository in this monorepo (`gcego/navigation:
^0.1`), discovered through Laravel package discovery. `gcego.css` already
scans `navigation/src/Presets` and `navigation/resources/views`.

To publish the configuration and views:

```bash
php artisan vendor:publish --tag=gcego-navigation-config
php artisan vendor:publish --tag=gcego-navigation-views
```

Interactive parts (groups, dropdowns, the mobile drawer, compact mode)
use Alpine.js, which Livewire bundles. There is no JavaScript to build.

## Documentation

| Topic | |
| --- | --- |
| [Architecture](docs/architecture.md) | definitions, trees, resolution, rendering, caching, contributions |
| [Items](docs/items.md) | items, routes, URLs, external links, badges, ordering |
| [Groups](docs/groups.md) | groups, nesting, dropdowns, headings, dividers |
| [Active states](docs/active-states.md) | route and path matching, patterns, query strings |
| [Breadcrumbs](docs/breadcrumbs.md) | trails, home, record labels |
| [Authorization](docs/authorization.md) | permissions, policies, tenants, default visibility, security |
| [Responsive](docs/responsive.md) | sidebar, top navigation, mobile drawer, Livewire |
| [Styling](docs/styling.md) | slots, presets, icons |
| [Accessibility](docs/accessibility.md) | landmarks, keyboard, focus, screen readers |
| [API reference](docs/api-reference.md) | everything, plus diagnostics |

## Testing

```bash
composer test     # Pest: definitions, trees, resolution, rendering, Alpine behavior (Node), Livewire, security, performance
composer analyse  # PHPStan (max)
```
