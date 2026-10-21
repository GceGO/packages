# gcego/core

The Laravel integration kernel of **GCEGO Admin Studio**. It adds one service
provider that:

- merges and validates `config/gcego.php`;
- tracks GCEGO's lifecycle inside Laravel (`Uninitialized → Registered → Booting → Booted`, or `Failed`);
- discovers installed GCEGO packages from their Laravel service providers and boots them **in dependency order**;
- activates switchable **extensions**, including optional cross-package integrations;
- previews startup without executing hooks, publishes typed startup events and records per-step timings;
- inspects lifecycle, discovery, dependencies, extensions, Laravel bindings, capabilities and operational configuration;
- accepts package-contributed health checks and emits redacted structured reports;
- reports structured **diagnostics**, including extension dependency checks, through PHP and `php artisan gcego:diagnose`;
- exposes everything through one injectable entry point, `Gcego\Core\Gcego`.

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
        { "type": "path", "url": "packages/components" },
        { "type": "path", "url": "packages/core" }
    ]
}
```

To publish the configuration:

```bash
php artisan vendor:publish --tag=gcego-config
```

## Usage

```php
use Gcego\Core\Gcego;

$gcego = app(Gcego::class);          // or inject Gcego / FrameworkInterface

$gcego->name();                      // "GCEGO Admin Studio"
$gcego->version()->version;          // from Composer, e.g. "1.0.0" or "dev-main"
$gcego->state();                     // FrameworkState::Booted
$gcego->isBooted();                  // true once Laravel has booted
$gcego->packages();                  // list<PackageDescriptor>
$gcego->isPackageActive('gcego/tables');
$gcego->capabilities();              // sorted capabilities from active packages and extensions
$gcego->hasCapability('tables');
$gcego->plan()->toArray();           // preview discovered packages and eligible extensions
$gcego->timings();                   // step timings, including failed attempts
$gcego->diagnose()->toArray();       // structured health report
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
