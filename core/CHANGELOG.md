# Changelog

All notable changes to `gcego/core` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [1.2.0] - 2026-10-21

### Added

- `Gcego\Core\Facades\Gcego` facade, auto-aliased as `\Gcego` through package discovery, covering every `FrameworkInterface` method with typed `@method` annotations. It resolves the contract, so container rebinding, `Gcego::swap()` and `Gcego::shouldReceive()` work although the framework class is final.

## [1.1.0] - 2026-10-21

See [releasing](docs/releasing.md#migrating-from-10-to-11) for upgrade steps.

### Changed

- Exclusive capabilities are now enforced before any hook runs. Two enabled packages providing a capability that either declares exclusive fail dependency resolution (`packages.capabilities.exclusive`); an extension that would give an exclusive capability a second provider fails startup before any extension activates (`extensions.capabilities.exclusive`). `gcego:plan` and diagnostics report the same conflicts. Previously, startup succeeded and only post-boot diagnostics flagged the conflict.
- The official framework name is **GCEGO Studio**: the default `gcego.name`, the `gcego/core` descriptor, the `framework.identity` diagnostic and package metadata use it.
- Dependency ordering builds and sorts the graph once per resolution using a min-heap: the same order, but 1,000 packages resolve in 1.8 ms instead of 35.6 ms.

### Fixed

- `extensions.dependencies` reports a warning that points at `packages.dependencies` when package dependencies are broken, instead of a failed "check could not run" result.

### Removed

- The `packages.components` diagnostic. Core no longer inspects whether `gcego/components` is installed; packages contribute their own checks through `DiagnosticRegistry`.

- `FrameworkDiagnostics::ComponentsPackageClass`, which no GCEGO package used.
- The `gcego/components` development dependency, its path repository and the test that used it. Core depends on no GCEGO package; `gcego/components` depends on Core.

### Added

- `PackageDependencyException::exclusiveCapability()` and `ExtensionException::exclusiveCapability()`.
- Documentation: package development guide with domain-neutral examples, security model, performance measurements, release and compatibility policy, and recovering from a failed automatic boot.

## [1.0.0] - 2026-10-20

### Fixed

- Close package registration immediately after provider discovery, before dependency resolution or package hooks; preserve that boundary across failed attempts and retries.
- Reject non-array root configuration during startup instead of silently substituting defaults.
- Include loaded package providers in diagnostics before manual boot without changing the live registry.
- Resolve transitive extension eligibility before dependency validation to avoid registration-order-dependent startup failures.
- Reject recursive manager initialization and registrations that would miss an already computed initialization plan; preserve recovery after failure.
- Retain deliberately disabled bootstrap outcomes in `bootResult()`.
- Locate component sources through the installed class and test version discovery against installed Composer metadata.

### Added

- Comprehensive Core diagnostics covering framework/environment identity, transition history, package/extension status, dependency integrity, registration incidents, Laravel integration, failure impact, capabilities, phase performance and production operational configuration.
- Stable diagnostic codes, severity, remediation, related components, UTC report timestamps, structured JSON serialization and metadata-free public summaries.
- Redaction of recognizable credentials and sensitive detail keys; reject nested/object diagnostic detail payloads and sanitize anonymous exception class names.
- Opt-in detailed profiling, lightweight phase/status records and configurable slow-initialization thresholds.
- Declared package version constraints, extension capability prerequisites and exclusive-capability integrity checks.
- Explicit console and PSR logging runtime dependencies, a combined `composer check` command, and distribution exclusions for workspace-only files.
- Immutable startup plans, isolated provider snapshots and `gcego:plan` with JSON output.
- Package-contributed health checks through `DiagnosticCheck` and `DiagnosticRegistry`, with isolated execution and collision protection.
- Typed framework, package and extension startup events through Laravel, with observer and logging failure isolation.
- Per-step timings and failure history retained across retries and included in completed bootstrap results.
- Dependency/dependant inspection and grouped active capability-provider lookup.
- `gcego:diagnose` Artisan command with table and JSON output, failure exit codes and strict warning handling.
- Extension dependency diagnostics without executing activation hooks.
- `Gcego::capabilities()` and `Gcego::extension()` with matching framework contract methods.
- `GcegoServiceProvider` with configuration merging, publishing (`gcego-config`), container bindings and automatic framework boot after Laravel boots.
- `Gcego` entry point implementing `FrameworkInterface`.
- `GcegoConfig` and `ConfigurationValidator` for validated, typed access to `config('gcego')`.
- `LifecycleManager` and `FrameworkState` with guarded transitions, failure recording and retry.
- `PackageInterface`, `Bootable`, `PackageDescriptor`, `PackageManager` and `PackageDependencyValidator`: provider-based package discovery, missing, disabled and circular dependency detection, and deterministic boot order.
- `ExtensionInterface`, `ExtensionDescriptor` and `ExtensionManager`: switchable capabilities with required, optional and package-conditional dependencies.
- `FrameworkBootstrapper`, `BootstrapContext` and `BootstrapResult`.
- `EnvironmentChecker`, `FrameworkDiagnostics`, `DiagnosticReport` and `DiagnosticResult`.
- `FrameworkVersion` backed by Composer runtime metadata.
- Exception hierarchy under `Gcego\Core\Exceptions`.
