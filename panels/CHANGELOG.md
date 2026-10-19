# Changelog

## Unreleased

- **Changed:** the document shell prints the tenant brand (`brandTag`, gcego/swatches) between the theme and the user's accent, and always calls `themeTag` (it emits only what the static CSS lacks). The sidebar and top bar use the `sidebar-*` and `topbar-*` color tokens (same colors by default). Tests configure appearance and accents through `gcego-swatches.*`.

- **Added:** a resource's `NavigationBadgeVariant` colors its count badge in the panel navigation.
- **Added:** the user menu lets users choose light, dark or system, and their own main color (`partials.appearance`); applied at once and remembered in their browser. Panel documents include the accent styles (`ChromaManager::accentTag()`).
- **Added:** the signed-in user at the foot of the sidebar (`partials.profile`, `Panel::ProfileSlot`): their picture (an `avatar_url` or `profile_photo_url` attribute that is a root-relative or https URL), else their initials, with name and email, opening the user menu (above it; beside the picture in the icon rail). `PanelShell::$userEmail`, `$userAvatar`, `userInitials()`. In the sidebar layouts the top bar's user menu is now for small screens, or while the sidebar is collapsed. The menu's contents are `partials.user-menu-items`.
- **Added:** panel navigation items of resources with `Resource::NavigationBadge` show their record count as a badge.
- **Added:** the sidebar narrows to an icon rail while its navigation is compact, and shows only the logo or monogram.
- **Added:** a remembered collapsed sidebar is applied before the first paint (`collapsePrelude()`).
- **Changed:** the sidebar's width animates (Tailwind utilities only): narrowing to the `w-17` icon rail (icons on its center line), and collapsing, where it now slides out (`SidebarCollapsed`, per layout: a negative start margin of its width, then `invisible`) instead of disappearing. The rail lets its links' tooltips show beside it. The brand name fades with the rail; the switcher hides its title in it.
- **Fixed:** the shell's inline scripts (appearance, theme, sidebar) carry the request's CSP nonce (`Vite::useCspNonce()`).
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## Unreleased

- `Contracts\PanelExtension` (`routes()`, `topbar()`, `body()`), registered with `PanelManager::extend()` or `gcego-panels.extensions`. Extension routes run inside each panel's route group, behind its middleware and access checks. Extension markup goes in every layout's top bar and at the end of the document. gcego/notifications uses it for the bell, toasts and inbox.
- `PanelManager::respond($title, $content)`: a panel document around trusted content, for extension routes.

## 0.1.0 - 2026-10-17

First release.

- **Definitions:** `Panel` (registry kind `panel`, `panel:<name>`, discovered from `app/GCEGo/Panels`), `ModuleReference`, `PageReference` (with a per-panel navigation opt-out), `Branding` (validated root-relative or https URLs) and `UserMenuItem`. A panel derives its navigation (gcego/registry `DerivesDefinitions`).
- **Routing:** `PanelRouteRegistrar` registers each panel's pages through gcego/pages' `PageRouteRegistrar`, plus a panel home. It covers prefixes, route name namespaces, domains, guards, verification, tenancy prefixes, fixed middleware order and conflict detection. Panel routes come from the registry index after boot and are skipped when routes are cached. `PanelRoutes` builds URLs.
- **Access:** deny by default. `AuthorizePanel` (`gcego.panel:<name>`, also Livewire persistent middleware) checks gcego/security requirements and `Contracts\PanelAccessPolicy`, and fails closed on errors. `PanelAccess` and `PanelAccessResult` decide.
- **Context:** a request-scoped `PanelContext` (panel, user, guard, tenant) in `PanelContextResolver`.
- **Navigation:** `PanelNavigationContributor` (gcego/navigation `MultipleNavigationsContributor`) adds the dashboard, the pages and the module groups, each carrying the page's requirements.
- **Shells:** `sidebar`, `topbar` and `compact` layouts with branding, a collapsible sidebar, gcego/navigation's mobile drawer, a user menu and a panel switcher (`PanelSwitcher`). They include a skip link and landmarks, use the panel's styling slots and themes (`PanelPresets`), and take Vite entries from `PanelAssetResolver`.
- **Pages integration:** while panels route pages, gcego/pages' own routing is off and its layout becomes the panel shell, unless the application set its own.
- **Diagnostics:** `PanelInspector` and `PanelReport`, and `PanelManager`.
- **Translations:** English and French.
