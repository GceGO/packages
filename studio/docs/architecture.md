# Architecture

```
Laravel package discovery ──> foundation providers (gcego/core … gcego/panels) + StudioServiceProvider
gcego/core (Gcego)         ──> registers and boots the GCEGO packages in dependency order
Studio                     ──> verifies, configures, extends, inspects; owns no engine
```

## Services

| Class | Role |
| --- | --- |
| `Studio` | the public entry point (`StudioInterface`) |
| `StudioManager` | resolves Studio's services lazily from the container |
| `Bootstrap\StudioBootstrapper` | applies extensions once after boot; `verify()` returns a `BootstrapResult` |
| `Integration\PackageIntegrationManager` | per-package status: installed, version, provider loaded, registered, active |
| `Integration\FrameworkCatalog` | panel, module, page and navigation reports from their own managers |
| `Compatibility\PackageCompatibilityChecker` | platform minimums and every GCEGO package's constraints |
| `Defaults\StudioDefaults` | feeds Studio values into package configuration |
| `Assets\StudioAssetManager` | read-only asset inspection |
| `Health\StudioHealthManager` | runs the checks, isolating failures |
| `Installation\InstallationManager` | plans and writes starter files |
| `Diagnostics\StudioInspector` | the inspector report, with redaction |
| `Extensions\StudioExtensionRegistry` | trusted extensions, applied once in deterministic order |

All of them are singletons or are resolved on demand, and none keeps request state. That makes them safe under Octane and in queue workers, where request data comes from the current request through each package's own scoped services.

## Lifecycle

1. **register:** Studio merges its configuration, binds its services and hooks `beforeResolving(PanelsConfig)` and `beforeResolving(RegistryConfig)` for its defaults. It also registers an `app->booted()` callback before gcego/panels does.
2. **boot:** the console commands and publishing are set up.
3. **booted:**
   - Studio adds the configured extensions and applies all of them (contexts are built and panel extensions passed to `PanelManager::extend()`);
   - then gcego/panels registers panel routes, unless routes are cached.

No step queries the database or reads definition files eagerly. The registry index is built when first needed, or read from `gcego:registry-cache`.

## Not duplicated

| Concern | Owner |
| --- | --- |
| authentication | Laravel / gcego/sentinel |
| authorization, tenancy | gcego/security |
| routing | gcego/pages, gcego/panels |
| discovery | gcego/registry (plus gcego/modules, gcego/panels) |
| rendering | gcego/runtime, Livewire |
| styling | gcego/chroma, Tailwind CSS v4 |
| navigation | gcego/navigation |
