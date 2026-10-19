# Changelog

## Unreleased

- **Changed:** badges over an icon use solid colors per variant (`NavigationPresets::IconBadgeVariants`, replacing `IconBadgeDefault`), so every variant stays readable on top of the icon.
- **Added:** a sidebar search (`gcego-navigation.sidebar.search`, default on): a field above the links filters them as the user types (every word must appear in a link's label or its groups' labels), hiding headings, dividers and empty groups, opening collapsed groups, with an empty state; Enter opens the first match, Escape clears, "/" focuses it. In the icon rail a search button opens the sidebar and focuses it. `NavigationView::sidebarState()` (`compactState()` is a deprecated alias), `keywords()`, `descendantKeywords()`.
- **Added:** `Item::BadgeCount` (an Eloquent model class): the badge is how many records the user may see, counted when the item renders (`NavigationContext::count()`: one aggregate query, policies and tenant scopes apply, zero shows nothing, `BadgeTtl` caches it). Default variant: `default`.
- **Changed:** the badge of a link with an icon floats over the icon's top-left corner as a small pill (`IconBadge`), in the expanded sidebar and the icon rail alike, so it never moves when the sidebar is toggled; default badges turn primary there (`NavigationBadge::classes(onIcon: true)`). Links without an icon keep the badge at their end. Top bar items are `relative` for it.
- **Added:** compact sidebar fixes:
  - Group chevrons hide, groups stay open so every icon stays reachable, links show their name as a tooltip, and the toggle icon turns.
  - A remembered compact state is applied before the first paint (`compactPrelude()`, with the request's CSP nonce), so the sidebar no longer flashes wide.
  - `NavigationBadge` takes typed Luminaries icons.
- **Changed:** the icon rail is pure CSS, keyed on the sidebar's `data-compact` (`group-data-[compact]/nav:` presets) instead of `x-bind:class` bindings:
  - Group headers and headings become thin dividers instead of empty rows; nested groups lose their indent.
  - Hovering or focusing a link shows a styled tooltip with its name (and badge) beside the rail (`Tooltip` preset), replacing the native `title`. Alpine drives it (`compactState()`: `hoverTip()`, `focusTip()`, `leaveTip()`): a short delay on the first hover, then instant while moving along the rail, at once on keyboard focus, dismissed by Escape or the toggle; it animates with `x-transition`.
  - Labels fade out and back in as the sidebar narrows and widens, and the toggle icon turns with the same easing.
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## Unreleased

- `Contracts\MultipleNavigationsContributor`: one contributor for several navigations (`contributesTo()`, `nodesFor()`), as gcego/panels uses for each panel's navigation.

## 0.1.0 - 2026-10-17

First release.

- `Navigation` (registered as "navigation:<name>", discovered from `app/GCEGo/Navigation`; a blueprint part) and the `NavigationNode` base.
- `Item` (route or path, parameters with `:tenant`, active patterns, query matching, badges, breadcrumb providers, tooltip, description, icon), `Group`, `Dropdown`, `Heading`, `Divider`, `ExternalLink`.
- `NavigationTree` and `NavigationTreeBuilder`: validation, ordering, maximum depth, cycle and duplicate detection, contributions (`NavigationContributor`).
- Request-time resolution: `VisibilityResolver` (gcego/security, default visibility), `RouteResolver`, `ActiveStateResolver` (segment-aware patterns, one active item), `BadgeResolver` (lazy providers, optional per-user and per-tenant cache), `BreadcrumbResolver` (`BreadcrumbProvider`, authorized record labels). Livewire updates resolve for the original page.
- Sidebar (collapsible groups, compact mode), top navigation (disclosure dropdowns), mobile drawer (modal dialog, focus trap, scroll lock, window events) and breadcrumb renderers; Blade components; styling slots; navigation icons.
- `NavigationManager` and `NavigationInspector`.
