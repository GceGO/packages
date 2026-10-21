# Changelog

All notable changes to `gcego/blueprints` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-10-21

Requires `gcego/core` ^1.0 and `gcego/registry` ^1.1 (class entrypoints and shared-folder attribution), both from Packagist.

### Changed (breaking)

- `Blueprint` is now a standalone `final readonly` value object built with `Blueprint::define(BlueprintDefinition ...$definitions)`. It no longer extends `Gcego\Components\Component`.
- Dependencies: `gcego/core`, `gcego/registry`, `illuminate/contracts` and `illuminate/support`. The `gcego/components` (`gcego/components`) dependency is removed.
- `BlueprintInterface` is the read-only definition API (`name()`, `title()`, `model()`, `table()`, `pages()`, `references()`, ...).
- `BlueprintValidator` now validates class references through `ReferenceRule` adapters. `BlueprintValidationError` carries a definition `key` instead of a component path and role.

### Added

- Expanded the built-in catalog with 51 optional class-reference definitions for layouts, navigation, data, security, API, automation, analytics, communication, media and integrations. All use the existing immutable reference validation and generic blueprint accessors.

- Definition classes `Name`, `Title`, `Description`, `Model`, `Table`, `Form`, `Infolist`, `Pages`, `Actions`, `Relations`, `Widgets` and `Derive`, built on `TextDefinition`, `ReferenceDefinition` and `ReferenceCollectionDefinition`.
- `DefinitionCatalog` and `DefinitionType`: a controlled extension mechanism for definition types from other packages.
- `BlueprintKind`, `BlueprintProvider` and `ProvidesBlueprint` for gcego/registry discovery of class-based module entrypoints.
- `BlueprintsServiceProvider` (a gcego/core package, capability `blueprints`) and `config/gcego-blueprints.php` (`kind`, `validate_references`).
- `ReferenceRules`, `Rules\ClassExists`, `Rules\ImplementsContract`; opt-in derived definitions through `BlueprintDeriver`.
- `InvalidDefinitionException`, `DefinitionRegistrationException`, `Naming::isClassName()` and `Naming::isDescription()`.

### Removed

- Component composition: `BlueprintRole`, `BlueprintPart`, `PartContainer`, `Composition\*`, `Inspection\*`, `Normalization\BlueprintNormalizer`, the `Icon`/`Slug` properties, and the composition exceptions.

### Definition catalog

- The built-in catalog is limited to definitions that GCEGO engines read: `Name`, `Title`, `Description`, `Icon` (new), `Model`, `Policy`, `Table`, `Form`, `Infolist`, `Pages`, `Actions`, `BulkActions`, `Relations`, `Widgets` and `Derive`. The `icon()`, `policy()` and `bulkActions()` accessors are new, and `icon` and `policy` are indexed as registry metadata.
- Removed 49 unreleased class-reference definitions:
  - Capabilities owned by other packages (navigation, permissions, panels, theme, settings, tenancy, search, audit, cache, translations, ...), so those packages can register typed definitions under those keys.
  - Duplicates of engine-level parts (`Tables`, `Forms`, `Infolists`, `Layouts`, `Columns`, `Fields`, `Entries`, `Filters`) and of `Model` (`DataSource`, `Repository`, `Query`, `Schema`).
  - Laravel application wiring (routes, controllers, middleware, commands, jobs, events, listeners, observers, mailables, notifications, webhooks).
- New building blocks for extension packages: `OptionsDefinition` with typed `Option`s, and `IdentifierListDefinition`.
- `references()` omits definitions that reference no class.

### Production hardening

- The registry kind is `blueprint` (`blueprint:subjects`), owned by this package. The `gcego-blueprints.kind` option was removed, because `module` belongs to gcego/modules. **Breaking** for anyone using the unreleased default `module:` identities.
- New `gcego-blueprints.discovery` options (`enabled`, `paths`, `entrypoint`). The provider adds its own `blueprint` roots before the registry reads its configuration, in any provider order. The entrypoint convention never matches `Module.php` files.
- `validate_references = false` is honored only in the `local` and `testing` environments.
- `ReferenceResolver` and `InvalidReferenceException`: checked access to references for engines that execute them.
- `ReferenceRules` locks on first validation, and `DefinitionCatalog` refuses required types after the first `Blueprint::define()`.
- Class entrypoints need no `return Subjects::class;` (gcego/registry finds the single provider class).

### Remaining integration work

- **gcego/pages, gcego/actions, gcego/tables, gcego/forms, gcego/infolists, gcego/layouts, gcego/data, gcego/navigation, gcego/widgets, gcego/runtime, gcego/chroma, gcego/cli, gcego/testing and gcego/modules** still use the removed component-based API (`BlueprintRole`, `BlueprintPart`, `PartContainer`, and `Blueprint` as a component). gcego/modules cannot boot against this version, so its integration is verified with a stand-in that reproduces its registry wiring, not the real package.
- **gcego/modules**: with the registry's shipped configuration (which lists `app/GCEGo/Modules` as `module` with the folder-name convention), `ModulesServiceProvider` skips adding its own `Module.php` root, so `Academics/Module.php` files are not discovered. This predates this release; the fix belongs in gcego/modules (compare the entrypoint as well as the path and kind).
