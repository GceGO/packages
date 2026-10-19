# API reference

All classes live under `Gcego\Core`.

## `Gcego` (final readonly, implements `Contracts\FrameworkInterface`)

Resolve it with `app(Gcego::class)`, `app(FrameworkInterface::class)`, or constructor injection.

| Method | Returns | Notes |
| --- | --- | --- |
| `name()` | `string` | `gcego.name` |
| `version()` | `Support\FrameworkVersion` | from Composer metadata |
| `config()` | `Configuration\GcegoConfig` | throws `ConfigurationException` when the config is invalid |
| `state()` | `Lifecycle\FrameworkState` | |
| `isEnabled()` / `isBooted()` | `bool` | |
| `boot()` | `Bootstrap\BootstrapResult` | idempotent; throws `BootstrapException` |
| `bootResult()` | `?BootstrapResult` | `null` before a successful boot |
| `packages()` | `list<PackageDescriptor>` | registered packages, in registration order |
| `hasPackage(string $id)` / `isPackageActive(string $id)` | `bool` | registered / booted |
| `package(string $id)` | `PackageDescriptor` | throws `PackageRegistrationException` when unknown |
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

`DependencyGraph` and `Identifier` are `@internal`.

## Exceptions

All implement `Exceptions\GcegoException`: `ConfigurationException`,
`InvalidLifecycleException`, `BootstrapException`,
`PackageRegistrationException`, `PackageDependencyException`,
`ExtensionException`. See [architecture](architecture.md#error-handling).
