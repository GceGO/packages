# Changelog

## Unreleased

- **Changed:** the page layout prints gcego/swatches' theme and tenant brand styles.

- **Fixed:** the page layout's appearance script carries the request's CSP nonce (`Vite::useCspNonce()`).
- The stylesheet budget test allows 61 KB (the sidebar icon rail).
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## Unreleased

- Page routes record their name prefix (`PageRouteRegistrar::PrefixKey`). When a page is routed under several prefixes (the same page in two gcego/panels panels), `PageRouteResolver::routeName()` prefers the current request's prefix, Livewire updates included (`currentPrefix()`), so links and redirects stay in the current panel.
- `PageLink::Parameters` accepts `":record"`, the current record's key (a view page linking to a page under its record, as gcego/modules' relationship pages).

## 0.1.0 - 2026-10-17

First release.

- `Page` (a gcego/blueprints Blueprint registered as "page:<name>", discovered from `app/GCEGo/Pages`; dotted names, registry scopes) and the page types `IndexPage`, `CreatePage`, `EditPage` (both `FormPage`s that add their form's action), `ViewPage`, `DashboardPage` and `CustomPage`.
- Page components: `Content` (escaped text) and `PageLink` (an authorized link to another page).
- Routing: one named GET route per page, registered from the registry index after the application's routes (`PageRouteRegistrar`), with prefixes, name prefixes, middleware, domains, `{record}` key constraints, `Where` constraints, conflict detection and route-cache support; `PageRouteResolver` for URLs.
- `PageLifecycle` (resolved, context created, authorized, record resolved, prepared, interaction, rendering, rendered, completed) with `PageLifecycleHook`s; `PageContext` and `RecordPageContext`.
- `PageAuthorizer`: page requirements with a configurable default (deny, authenticated, public), child requirements, scoped record lookup and record abilities; guests get an authentication exception.
- Headers (`PageTitle`, `PageDescription`, `PageTitleProvider`), breadcrumbs (`PageBreadcrumbs`, gcego/navigation breadcrumbs, optional navigation trail), header actions and post-action navigation (`PageActionResolver`).
- `PageRenderer` (gcego/runtime), page views with styling slots, empty, loading and missing-record states, the default layout, and the reusable Livewire `PageHost`.
- `PageNavigationContributor` for gcego/navigation, `PageManager` and `PageInspector`.
