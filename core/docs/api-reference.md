# API reference

All classes live under `Gcego\Core`.

## `Gcego` (final readonly, implements `Contracts\FrameworkInterface`)

Resolve it with `app(Gcego::class)`, `app(FrameworkInterface::class)`, or constructor injection,
or call it statically through the facade.

## `Facades\Gcego` (final, extends `Illuminate\Support\Facades\Facade`)

Auto-aliased as `\Gcego` through Laravel package discovery. Every method in the table
below is available statically (`Gcego::isBooted()`) with full `@method` types for IDEs
and static analysis. The facade resolves `FrameworkInterface`, so it follows whichever
implementation the container binds. `Gcego::shouldReceive()` and `Gcego::swap()` mock
the contract, because the `Gcego` class itself is final. Core's own classes use
constructor injection, never the facade.

| Method | Returns | Notes |
| --- | --- | --- |
| `name()` | `string` | `gcego.name` |
| `version()` | `Support\FrameworkVersion` | from Composer metadata |
| `config()` | `Configuration\GcegoConfig` | throws `ConfigurationException` when the config is invalid |
| `state()` | `Lifecycle\FrameworkState` | |
| `isEnabled()` / `isBooted()` | `bool` | |
| `boot()` | `Bootstrap\BootstrapResult` | idempotent; throws `BootstrapException` |
| `plan()` | `BootstrapPlan` | preview from registered packages and loaded providers, without hooks |
| `timings()` | `list<StartupTiming>` | all recorded initialization attempts, including failures |
| `packageDependencies(string $id, bool $transitive = false, bool $includeOptional = false)` | `list<string>` | sorted dependency IDs from live metadata |
| `packageDependants(string $id, bool $transitive = false, bool $includeOptional = false)` | `list<string>` | sorted registered dependant IDs |
| `capabilityProviders(string $capability)` | `array{packages: list<string>, extensions: list<string>}` | sorted active provider IDs |
| `bootResult()` | `?BootstrapResult` | `null` before a completed boot attempt; retains disabled outcomes |
| `packages()` | `list<PackageDescriptor>` | registered packages, in registration order |
| `hasPackage(string $id)` / `isPackageActive(string $id)` | `bool` | registered / booted |
| `package(string $id)` | `PackageDescriptor` | throws `PackageRegistrationException` when unknown |
| `capabilities()` | `list<string>` | unique active capabilities, sorted by name |
| `extension(string $id)` | `ExtensionDescriptor` | registered extension; throws when unknown |
| `extensions()` | `list<ExtensionDescriptor>` | registered extensions |
| `hasExtension(string $id)` / `isExtensionActive(string $id)` | `bool` | registered / activated |
| `hasCapability(string $capability)` | `bool` | from active packages and extensions |
| `diagnose()` | `Diagnostics\DiagnosticReport` | never throws |

Constant: `Gcego::PackageName = 'gcego/core'`.

## `GcegoServiceProvider` (final, implements `PackageInterface`)

`register()`, `boot()`, `descriptor()` (`gcego/core` with capability
`framework`), `static configPath(): string`. Constant `ConfigTag = 'gcego-config'`.

## Contracts

| Interface | Methods |
| --- | --- |
| `FrameworkInterface` | the `Gcego` methods above |
| `PackageInterface` | `descriptor(): PackageDescriptor` |
| `Bootable` | `bootPackage(BootstrapContext $context): void` |
| `ExtensionInterface` | `descriptor(): ExtensionDescriptor`, `activate(BootstrapContext $context): void` |

## Configuration

**`GcegoConfig`** (final readonly): `static fromArray(array $config, ConfigurationValidator $validator = new ConfigurationValidator)`,
`static defaults()`, `enabled()`, `name()`, `autoBoot()`, `environmentChecks()`,
`disabledPackages()`, `isPackageDisabled(string)`, `disabledExtensions()`,
`isExtensionDisabled(string)`, `toArray()`.

**`ConfigurationValidator`** (final): `validate(array $config): array{errors: list<string>, warnings: list<string>, values: array}`.
Constant `MaxNameLength = 120`.

## Lifecycle

**`FrameworkState`** (enum, string-backed): `Uninitialized`, `Registered`,
`Booting`, `Booted`, `Failed`; `canTransitionTo(FrameworkState): bool`.

**`LifecycleManager`** (final): `state()`, `is(FrameworkState)`, `isBooted()`,
`failure(): ?Throwable`, `markRegistered()`, `beginBoot()`, `completeBoot()`,
`cancelBoot()`, `fail(Throwable)`, `transitionTo(FrameworkState)`.

## Bootstrap

**`FrameworkBootstrapper`** (final): `boot(): BootstrapResult`, `result(): ?BootstrapResult`.

**`BootstrapContext`** (final readonly): public `app` (`Illuminate\Contracts\Foundation\Application`),
`config` (`GcegoConfig`), `packages` (`list<string>`, in boot order),
`capabilities` (`list<string>`); `hasPackage(string)`, `hasCapability(string)`.

**`BootstrapResult`** (final readonly): public `status` (`BootStatus`), `packages`,
`extensions`, `skippedExtensions`, `durationMs`; `static disabled()`,
`isBooted()`, `toArray()`.

**`BootStatus`** (enum): `Booted`, `Disabled`.

## Packages

**`PackageDescriptor`** (final readonly):
`__construct(string $id, ?string $version = null, list<string> $requires = [], list<string> $optional = [], list<string> $capabilities = [], ?string $description = null)`;
public properties of the same names; `withVersion(?string)`, `hasCapability(string)`, `toArray()`.

**`PackageManager`** (final): `register(PackageInterface)`, `has(string)`,
`get(string)`, `descriptor(string)`, `descriptors()`, `isBooted(string)`,
`booted(): list<string>`, `resolve(list<string> $disabled = []): list<string>`,
`problems(list<string> $disabled = []): list<PackageDependencyException>`,
`boot(list<string> $order, BootstrapContext)`, `lock()`, `isLocked()`.

**`PackageDependencyValidator`** (final): `resolve(list<PackageDescriptor>, list<string> $disabled = []): list<string>`,
`problems(list<PackageDescriptor>, list<string> $disabled = []): list<PackageDependencyException>`.

## Extensions

**`ExtensionDescriptor`** (final readonly):
`__construct(string $id, list<string> $requires = [], list<string> $optional = [], list<string> $whenPackages = [], list<string> $capabilities = [], ?string $description = null)`;
`hasCapability(string)`, `toArray()`.

**`ExtensionManager`** (final): `register(ExtensionInterface)`, `has(string)`,
`get(string)`, `descriptor(string)`, `descriptors()`, `isActive(string)`,
`active(): list<string>`, `skipped(): array<string, string>`,
`plan(list<string> $disabled, list<string> $activePackages): array{order: list<string>, skipped: array<string, string>}`,
`activate(BootstrapContext, list<string> $disabled = [])`, `lock()`, `isLocked()`.

## Diagnostics

**`EnvironmentChecker`** (final readonly): `__construct(Application $app, string $phpVersion = PHP_VERSION, ?list<string> $loadedExtensions = null)`,
`check(): list<DiagnosticResult>`, `failures(): list<DiagnosticResult>`.
Constants `MinimumPhpVersion`, `MinimumLaravelVersion`, `RequiredExtensions`.

**`FrameworkDiagnostics`** (final readonly): `run(): DiagnosticReport`.

**`DiagnosticReport`** (final readonly, `Countable`, `IteratorAggregate`):
`results()`, `result(string $check)`, `failures()`, `warnings()`,
`hasFailures()`, `hasWarnings()`, `isHealthy()`, `toArray()`, `count()`.

**`DiagnosticResult`** (final readonly): public `check`, `status`, `message`,
`details`; `static passed()`, `static warning()`, `static failed()`,
`isFailure()`, `toArray()`.

**`DiagnosticStatus`** (enum): `Passed`, `Warning`, `Failed`.

## Support

**`FrameworkVersion`** (final readonly, `Stringable`): public `package`,
`version`, `reference`; `static detect(string $package = 'gcego/core')`,
`isDevelopment()`, `toString()`. Constant `Unknown = 'dev'`.

`DependencyGraph`, `Identifier` and `ExclusiveCapabilities` are `@internal`.

## Exceptions

All implement `Exceptions\GcegoException`: `ConfigurationException`,
`InvalidLifecycleException`, `BootstrapException`,
`PackageRegistrationException`, `PackageDependencyException`,
`ExtensionException`. See [architecture](architecture.md#error-handling).
`PackageDependencyException` and `ExtensionException` expose `diagnosticCode`;
both add `static exclusiveCapability(string $capability, list<string> $providers)` in 1.1.0.

## Planning, health extensions and observability

- `BootstrapPlan`: immutable preview with `enabled`, `packages`, `extensions`,
  `skippedExtensions`, `disabledPackages`, `capabilities`, `problems`,
  `isValid()` and `toArray()`. See [startup planning](startup-planning.md).
- `StartupTiming`: immutable `kind`, `id`, `durationMs`, `succeeded`, `toArray()`.
  `BootstrapResult::timings` is a snapshot of timing history.
- `Contracts\DiagnosticCheck`: `id(): string`, `run(): DiagnosticResult`.
- `DiagnosticRegistry`: `register(DiagnosticCheck): void`,
  `run(): list<DiagnosticResult>`. Invalid registration throws
  `DiagnosticException`; check execution errors become failed results.
- Typed event payloads and their semantics are documented in
  [startup events](startup-events.md).
- `PackageManager::dependencies()` and `dependants()` accept the same arguments
  as their framework entry-point counterparts. `snapshot()` copies cached
  registrations into an unlocked manager without boot state.
- `PackageDiscovery::discover()` registers loaded providers in the live manager;
  `snapshot()` returns a temporary manager including loaded providers.
- `StartupPlanner::plan()` implements preview generation.
- `StartupTelemetry::timings()` returns accumulated timing records.
  `publish(object)` dispatches observations with failure isolation.
  `measure('package'|'extension', string, callable(): void)` records a step and
  rethrows initialization failures after publishing the failure event.

## Comprehensive diagnostics and declaration metadata

`DiagnosticResult` additionally exposes `severity()` and
`recommendedResolution()`. Its constructor/factories accept optional
`resolution`, `package` and `extension` arguments. Structured rows retain
`check`, `status`, `message`, `details` and add `code`, `severity`, `resolution`,
`package`, `extension`. Details remain scalars or lists of scalars.

`DiagnosticReport` adds `generatedAt` (UTC), `health()` (`healthy`, `warning`,
`critical`), `toStructuredArray()` (the report envelope), and `publicSummary()`
(health and timestamp only). Both result/report implement `JsonSerializable`.
`toArray()` retains the list-of-rows shape. `isHealthy()` still means no errors.

`LifecycleManager::history()` returns the latest 100 accepted/rejected transitions
as `{from, to, accepted, timestamp}` records. `InfrastructureIssues` stores the
latest 100 sanitized incident records. `IntegrationState` tracks Core provider
and command-registration milestones. `CoreDiagnostics` supplies built-in
infrastructure checks; `FrameworkDiagnostics` isolates groups and merges custom
checks. These services inspect Core only, without adding HTTP endpoints.

`PackageDescriptor` adds `versionConstraints` (`array<string, string>`) and
`exclusiveCapabilities` (`list<string>`). `ExtensionDescriptor` adds
`requiresCapabilities` and `exclusiveCapabilities` (`list<string>`).
`ExtensionManager::plan()` accepts optional package capabilities as its third
argument. Version/dependency exceptions expose `diagnosticCode` for stable
machine-readable failure categories.

`GcegoConfig` adds `profiling()`, `slowBootMs()`, `slowPackageMs()`,
`slowExtensionMs()`. `StartupTelemetry` adds `steps()`, `phases()`, `durationMs()`
and profiling state. Essential status is independent of opt-in timing history.
Package snapshots may preserve registration locks using `snapshot(true)`; the
discovery inspector uses this form so previews cannot reopen a closed package set.
