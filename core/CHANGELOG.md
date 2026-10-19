# Changelog

All notable changes to `gcego/core` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

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
