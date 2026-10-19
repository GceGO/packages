# Changelog

## Unreleased

- **Added:** `Resource::NavigationBadgeVariant`: the color of the resource's count badge (default, primary, success, warning, danger, info). The count reads as "12 <navigation label>" for screen readers. `ResourceDefinition::$navigationBadgeVariant`.
- **Added:** `Resource::NavigationBadge` (default `false`): the resource's navigation item shows how many records the user may see (`Item::BadgeCount`). `ResourceDefinition::$navigationBadge`.
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## 0.1.0 - 2026-10-17

First release.

- **Definitions:** `Module` and `Resource`, compiled by `ModuleCompiler` (validate, extend, normalize, derive pages) into `ModuleDefinition` and `ResourceDefinition`.
- **Registry:** the `module` registry kind with discovery roots (`Module.php`). Derived pages are indexed as `page` definitions (gcego/registry `DerivesDefinitions`).
- **Pages:** generated index, create, view and edit pages built from the declared Table, Form, Infolist and actions, with deterministic names, routes and paths, overrides and custom pages.
- **Authorization:** module and resource requirements, per-operation permissions and default model policies.
- **Relationships:** `Relation` managers (`ParentScope`, `ParentAccess`, `RelatedRecords`), and `Attach`/`Detach` for BelongsToMany.
- **Navigation:** module groups (`ModuleNavigationContributor`).
- **Context:** `ModuleContext` and `ResourceContext`.
- **Extensions, hooks and diagnostics:** `ModuleExtension`, `ModuleLifecycleHook` and `ModuleInspector`.
- **Translations:** English and French.
