# gcego/core

The Laravel integration kernel of **GCEGO Studio**, a universal, modular
Laravel administration framework. Core is domain-agnostic: it coordinates
packages and never assumes what kind of application they build. It adds one
service provider that:

- merges and validates `config/gcego.php`;
- tracks GCEGO's lifecycle inside Laravel (`Uninitialized → Registered → Booting → Booted`, or `Failed`);
- discovers installed GCEGO packages from their Laravel service providers and boots them **in dependency order**;
- activates switchable **extensions**, including optional cross-package integrations;
- previews startup without executing hooks, publishes typed startup events and records per-step timings;
- inspects lifecycle, discovery, dependencies, extensions, Laravel bindings, capabilities and operational configuration;
- accepts package-contributed health checks and emits redacted structured reports;
- reports structured **diagnostics**, including extension dependency checks, through PHP and `php artisan gcego:diagnose`;
- exposes everything through one entry point: inject `Gcego\Core\Gcego` or use the `Gcego` facade.

Core renders nothing. It registers no routes, views, Livewire components or
styles, and it has no component registry. Those belong to later GCEGO packages.

## Requirements

- PHP 8.3+
- Laravel 13 (`illuminate/support` and `illuminate/contracts` ^13.0)

## Installation

```bash
composer require gcego/core
```

Laravel auto-discovers `Gcego\Core\GcegoServiceProvider`, so nothing else is
needed. Inside the GCEGO monorepo, add a path repository first:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/core" }
    ]
}
```

To publish the configuration:

```bash
php artisan vendor:publish --tag=gcego-config
```

## Usage

The `Gcego` facade is auto-aliased, so routes, Blade views and other
non-namespaced code can use it without an import:

```php
Gcego::isBooted();                   // true once Laravel has booted
Gcego::hasCapability('reports');     // from active packages and extensions
Gcego::diagnose()->isHealthy();
```

Classes you write can depend on the same API by injection instead:

```php
use Gcego\Core\Contracts\FrameworkInterface;

public function __construct(private FrameworkInterface $gcego) {}
```

Every facade method is a method of `FrameworkInterface`:

```php
use Gcego\Core\Facades\Gcego;

Gcego::name();                       // "GCEGO Studio"
Gcego::version()->version;           // from Composer, e.g. "1.0.0" or "dev-main"
Gcego::state();                      // FrameworkState::Booted
Gcego::isBooted();                   // true once Laravel has booted
Gcego::packages();                   // list<PackageDescriptor>
Gcego::isPackageActive('gcego/tables');
Gcego::capabilities();               // sorted capabilities from active packages and extensions
Gcego::hasCapability('tables');
Gcego::plan()->toArray();            // preview discovered packages and eligible extensions
Gcego::timings();                    // step timings, including failed attempts
Gcego::diagnose()->toArray();        // structured health report
```

## Making a package a GCEGO package

Implement `PackageInterface` on the package's existing Laravel service provider:

```php
use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\Bootable;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Illuminate\Support\ServiceProvider;

final class TablesServiceProvider extends ServiceProvider implements PackageInterface, Bootable
{
    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/tables',
            requires: ['gcego/core'],
            optional: ['gcego/security'],
            capabilities: ['tables'],
        );
    }

    public function bootPackage(BootstrapContext $context): void
    {
        // Runs once, after gcego/core (and gcego/security when installed).
    }
}
```

That is all: there is no second registration step and no package list to
maintain. See [packages](docs/packages.md) and [extensions](docs/extensions.md).

## Documentation

| | |
| --- | --- |
| [Architecture](docs/architecture.md) | Design decisions, container bindings, boundaries |
| [Installation](docs/installation.md) | Composer, discovery, publishing |
| [Configuration](docs/configuration.md) | Every option and how it is validated |
| [Startup planning](docs/startup-planning.md) | Read-only previews, dependency and capability-provider queries |
| [Startup events](docs/startup-events.md) | Typed events, timing and observer failure semantics |
| [Lifecycle](docs/lifecycle.md) | States, boot sequence, failure and retry |
| [Packages](docs/packages.md) | Package contract, dependencies, ordering |
| [Extensions](docs/extensions.md) | Switchable capabilities and optional integrations |
| [Diagnostics](docs/diagnostics.md) | Environment and framework checks |
| [Developing packages](docs/developing-packages.md) | Third-party package guide with worked examples |
| [Security](docs/security.md) | Trust model, guarantees and limitations |
| [Performance](docs/performance.md) | Measured overhead and benchmarks |
| [Releasing](docs/releasing.md) | Versioning, compatibility policy, migration, testing |
| [API reference](docs/api-reference.md) | Every public class and method |

## Development

```bash
composer check       # manifest validation, formatting, static analysis and tests
composer test        # Pest + Orchestra Testbench
composer analyse     # PHPStan (Larastan), level max
composer format      # Laravel Pint
composer benchmark   # registration, boot, dependency resolution, container resolution
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
