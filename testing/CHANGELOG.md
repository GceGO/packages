# Changelog

## Unreleased

- **Fixed:** `TestApplicationFactory::Providers` and `PackageCompatibility` include gcego/settings, gcego/analytics, gcego/studio and gcego/cli, so test applications built with `PackageTestCase` load them when installed (gcego/sentinel stays opt-in: it registers the authentication routes and needs its tables).
- The analytics assertions run in this package's own suite (gcego/analytics is a dev dependency).
- README: removed the outdated note that gcego/studio and gcego/settings do not exist.

## 0.1.0 - 2026-10-18

First release.

- **Entry points**:
  - `GcegoTest`, a static entry point over `TestingManager`.
  - One fluent testable per layer: component, blueprint, registry, runtime, styling, data, security, tenant, action, form, table, infolist, widget, layout, navigation, page, module, panel, notification, inbox, media, workflow (instance and definition), analytics (definitions), HTML, accessibility, performance, contracts.
- **Assertion foundation**:
  - `Verifier`: PHPUnit failures, passes counted as assertions.
  - `AssertionFailureFormatter`: subject, context, expected, actual, suggestion.
  - `Redactor`: sensitive keys masked at any depth; configurable patterns.
- **Production paths only**:
  - actions: `ActionManager::execute()`, including the signed confirmation-token round trip;
  - forms: `FormManager::process()`;
  - tables: `TableManager`, with strict state normalization;
  - pages: `PageManager::open()`;
  - panels: `PanelAccess::decide()`;
  - security: `SecurityManager` and `TenantAuthorizer`;
  - data: `DataManager`.
- **Tenant isolation**: `TenantTestable` covers:
  - membership checks;
  - cross-tenant refusal;
  - visibility by listing and by key;
  - foreign actions and bulk selections;
  - context restoration.
- **Livewire**:
  - `LivewireTestAdapter` wraps `Livewire::test()` as a chosen user and tenant, with mount-refusal and locked-property assertions.
  - `LivewireRequests` sends real update requests through the HTTP kernel, plus snapshot extraction and forging.
- **Fixtures**:
  - `FixtureManager`, `Workspace` (parallel-safe, removed on cleanup) and `RegistryFixture` (isolated discovery);
  - `DefinitionFactory` (14 representative definitions);
  - `TestUserFactory`, plus `TestTenantFactory` over an application `TenantFixtureProvider`.
- **Performance**:
  - `QueryCounter` and `PerformanceRecorder`;
  - query count, duplicate and N+1-shape detection;
  - memory, duration, payload and cache-hit assertions.
- **Accessibility**:
  - `StaticAccessibilityChecker`, with 14 markup rules and browser-order accessible names;
  - optional `BrowserAccessibility` (axe-core in Playwright Chromium), which throws instead of passing when unavailable.
- **Environment**:
  - `TestingEnvironment`: acting user and tenant, new requests, binding the current page.
  - `TestStateResetter`.
  - `TestApplicationFactory`: providers in dependency order, isolated configuration, parallel cache prefix.
  - `PackageTestCase` for Orchestra Testbench.
- **Integrations**:
  - PHPUnit traits (`InteractsWithGcego` and four focused traits);
  - optional Pest expectations (`PestIntegration::register()`).
- **Diagnostics**:
  - `TestingInspector`: dependencies, database, isolation risks, tenancy, fixtures, parallel testing, browser tools, packages.
  - `PackageCompatibility`: Composer's installed versions and registered providers.
  - `ContractTestable`: component, renderer, styling resolver, tenant resolver and permission provider contracts.
- **Configuration**: `config/gcego-testing.php`.
- **Not included**:
  - Studio and settings assertions (the packages do not exist yet).
  - Analytics report execution (the engine is still being built).
