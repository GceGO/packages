# Architecture

`gcego/core` is GCEGO Admin Studio's Laravel kernel. It coordinates the GCEGO
packages installed in an application and builds on Laravel's own primitives:

| Concern | Provided by |
| --- | --- |
| Dependency injection | Laravel's container |
| Package installation and versions | Composer |
| Package loading | Laravel service providers and package discovery |
| Configuration storage and caching | Laravel's config repository and `config:cache` |
| GCEGO readiness, dependency-ordered package boot, extensions, diagnostics | **gcego/core** |

## Class map

```text
Gcego\Core
├── Gcego                      entry point (FrameworkInterface)
├── GcegoServiceProvider       Laravel integration; is itself the "gcego/core" package
├── Contracts\                 FrameworkInterface, PackageInterface, Bootable, ExtensionInterface
├── Configuration\             GcegoConfig (typed view), ConfigurationValidator
├── Lifecycle\                 FrameworkState, LifecycleManager
├── Bootstrap\                 FrameworkBootstrapper, BootstrapContext, BootstrapResult, BootStatus
├── Packages\                  PackageDescriptor, PackageManager, PackageDependencyValidator
├── Extensions\                ExtensionDescriptor, ExtensionManager
├── Diagnostics\               EnvironmentChecker, FrameworkDiagnostics, DiagnosticReport,
│                              DiagnosticResult, DiagnosticStatus
├── Support\                   FrameworkVersion, DependencyGraph (internal), Identifier (internal)
└── Exceptions\                GcegoException (interface) and six concrete exceptions
```

### Decisions and deviations from the original plan

- **No `GcegoManager`.** `Gcego` is the entry point and holds no state; each
  concern is owned by one service (`LifecycleManager`, `PackageManager`,
  `ExtensionManager`, `GcegoConfig`). A separate manager would have been a
  second facade over the same state.
- **No `ConfigurationResolver`.** Laravel's `mergeConfigFrom()` merges the
  defaults. `ConfigurationValidator` validates and interprets the values, and
  `GcegoConfig` is the typed result.
- **No `Configurable` contract and no `LifecycleEvent`.** Neither had a
  concrete consumer. Packages hook into the boot through `Bootable`, and
  readiness is queried with `Gcego::state()`. No event bus was added.
- **No static facade.** `Gcego` is resolved from the container or injected,
  never used as a global service locator.
- **Added `Bootable`.** Laravel boots providers in registration order. GCEGO
  adds an optional boot hook that runs in **dependency order** after every
  provider has booted. This is the one capability Laravel lacks.
- **Added `DependencyGraph`.** One iterative topological sort is shared by
  packages and extensions.
- **Environment variables use the `GCEGO_STUDIO_` prefix.** The host
  application already uses `GCEGO_*` for its own features.
- **No `debug` option.** No behaviour needed one.

## Package discovery without a package list

`FrameworkBootstrapper` calls `$app->getProviders(PackageInterface::class)`,
which returns the loaded providers that implement the interface. Composer
installs the package, Laravel discovers and loads its provider, and GCEGO
recognizes it. No filesystem scan, cache file or manual list is involved.
Objects that are not providers can call `PackageManager::register()` instead.

Providers implementing `PackageInterface` must not be deferred, because
deferred providers are not loaded at boot time.

## Container bindings

| Service | Lifetime | Notes |
| --- | --- | --- |
| `Gcego` (alias `FrameworkInterface`) | singleton | stateless facade |
| `LifecycleManager` | singleton | resolved in `register()` to mark Registered |
| `PackageManager` | singleton | framework-wide metadata |
| `ExtensionManager` | singleton | framework-wide metadata |
| `FrameworkBootstrapper` | singleton | caches the boot result |
| `GcegoConfig` | singleton | built on first use from `config('gcego')` |
| `FrameworkVersion` | singleton | read once from Composer's runtime metadata |
| `EnvironmentChecker`, `FrameworkDiagnostics`, `ConfigurationValidator` | transient | stateless; built on demand |

No scoped services exist, because nothing in core is request-specific.
Singletons hold only framework-wide, boot-time metadata. They never hold the
request, user, session or tenant, which a test verifies across HTTP requests.
That makes them safe under Octane and queue workers: the framework boots once
per worker and is not booted again per request.

`register()` resolves nothing except `LifecycleManager`, which has no
dependencies. Everything else is resolved lazily.

## Configuration strategy

`config/gcego.php` is the only place `env()` is called, so values survive
`config:cache`. Runtime services read the repository (a test asserts that no
`src/` file calls `env()`). `GcegoConfig` is built once, on first use. That
normally happens during framework boot, after every provider has had the chance
to adjust the configuration.

## Error handling

All exceptions implement `Gcego\Core\Exceptions\GcegoException`.

| Exception | Base | When |
| --- | --- | --- |
| `ConfigurationException` | `UnexpectedValueException` | invalid `config('gcego')`; lists every key, never values |
| `InvalidLifecycleException` | `LogicException` | illegal state transition, for example a re-entrant boot |
| `BootstrapException` | `RuntimeException` | any boot failure; the cause is preserved as `getPrevious()` |
| `PackageRegistrationException` | `LogicException` | invalid descriptor, duplicate id, late registration, unknown id |
| `PackageDependencyException` | `LogicException` | missing, disabled or circular package dependencies |
| `ExtensionException` | `LogicException` | the same, for extensions |

## Dependency boundaries

- Runtime dependencies: `illuminate/support`, `illuminate/contracts` and
  `composer-runtime-api` only. No Livewire, Blade, Tailwind or Filament.
- `gcego/components` is a **development** dependency, used to prove that real
  component definitions work in a GCEGO application. Core has no runtime use
  for it. Packages that build components require it themselves.
- The dependency direction is one-way: `gcego/components` never imports
  `gcego/core`, and a test enforces this.
- Core is not a registry. It knows packages and extensions, never components
  or application definitions.
