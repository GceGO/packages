# Architecture

gcego/modules is an orchestration layer. It owns module and resource definitions and turns them into definitions the other packages already understand. It renders nothing, runs no query of its own and adds no router, page engine or action engine.

| Concern | Owner |
| --- | --- |
| Module and resource definitions, normalization, page derivation, relationship constraints, navigation contribution, module diagnostics | gcego/modules |
| Discovery, identity, caching, lazy loading | gcego/registry (`module` kind; derived `page` definitions) |
| Routes, page lifecycle, record lookup, headers, breadcrumbs, `PageHost` | gcego/pages |
| Lists, forms, details, actions | gcego/tables, gcego/forms, gcego/infolists, gcego/actions |
| Queries, scopes | gcego/data |
| Requirements, policies, tenants | gcego/security |
| Menus | gcego/navigation |
| Rendering, styling | gcego/runtime, gcego/chroma |

## Flow

1. **Discovery.** gcego/registry finds `Module.php` files in `gcego-modules.discovery.paths`. The service provider adds those folders as registry roots of kind `module`.
2. **Registration.** The registry's loader expands definitions implementing `Gcego\Registry\Contracts\DerivesDefinitions`. `Module` implements it: its pages are indexed next to it as `page` definitions, with the same source file and scope, and are loaded lazily the same way.
3. **Compilation.** `ModuleCompiler` turns a module into a `ModuleDefinition` in stages:
   1. validate (component rules);
   2. apply extensions;
   3. normalize: names, paths, operations, Sources, requirements and relations;
   4. derive pages (`ResourcePageFactory`).

   It is pure (no I/O) and memoized per definition object.
4. **Routing.** gcego/pages registers one route per page from the registry index, when the application boots.
5. **Requests.** gcego/pages opens the page: requirements, record lookup and authorization. The page's tables, forms, infolists and actions run in their own Livewire hosts. Relation pages add `ParentAccess` (a requirement) and `ParentScope` (a data constraint).
6. **Navigation.** `ModuleNavigationContributor` adds module groups to a gcego/navigation navigation.

## Changes to other packages

| Package | Change |
| --- | --- |
| gcego/registry | `Contracts\DerivesDefinitions` and `Loading\DerivedDefinitions`. Derived definitions are indexed with their parent's source and scope, and registered with it at runtime. |
| gcego/pages | `PageLink::Parameters` accepts `":record"` (the current record's key), so a view page can link to a relationship page. |
