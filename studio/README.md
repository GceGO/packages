# gcego/studio

GCEGO Admin Studio: the unified entry point that assembles the GCEGO foundation packages into one admin framework for Laravel 13, Livewire 4 and Tailwind CSS v4.

Studio owns no engine. Authentication stays with Laravel (gcego/sentinel or your own login), routing with gcego/panels and gcego/pages, rendering with gcego/runtime and Livewire, styling with gcego/chroma, and authorization and tenancy with gcego/security. Studio adds the parts an application needs on top:

- one entry point (`Studio`) over packages, panels, modules, pages and capabilities;
- an installer (`php artisan gcego:install`) that is idempotent and never overwrites your files silently;
- typed configuration that feeds the packages' documented settings;
- health, compatibility and production-readiness checks (`gcego:studio:health`);
- an inspector (`gcego:studio:inspect`);
- trusted extensions.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [The Studio entry point](#the-studio-entry-point)
- [Configuration](#configuration)
- [Health and production readiness](#health-and-production-readiness)
- [Inspecting the framework](#inspecting-the-framework)
- [Extensions](#extensions)
- [Security](#security)
- [Caching and deployment](#caching-and-deployment)
- [Testing](#testing)
- Further documentation: [docs/](docs/)

## Requirements

- PHP 8.3+ (tested on 8.5), Laravel 13, Livewire 4, Tailwind CSS v4 with Vite;
- the eighteen foundation packages, gcego/components through gcego/panels (Composer installs them);
- Laravel authentication: gcego/sentinel or your own `login` route.

Optional packages are detected, not required: gcego/notifications, media, workflows, settings, analytics, testing, cli and sentinel.

## Installation

```bash
composer require gcego/studio
php artisan gcego:install
```

`gcego:install` creates three files, and reports and keeps any file that already exists with different contents:

| File | Purpose |
| --- | --- |
| `config/gcego-studio.php` | Studio's configuration |
| `app/GCEGo/Panels/admin.php` | a starter panel, guarded by the `access-admin` Gate ability |
| `app/GCEGo/Pages/dashboard.php` | its dashboard |

Options:

| Option | Effect |
| --- | --- |
| `--panel=staff` | name (and path) of the starter panel |
| `--model="App\Models\Subject"` | also create a read-only example module for an existing model |
| `--module=Academics` | the example module's folder (default: the model's plural) |
| `--dry-run` | show the plan, write nothing |
| `--force` | overwrite files whose contents differ |
| `--no-interaction` | never ask; differing files are kept |

The installer creates no users, roles or permissions. **Nobody can enter the starter panel until you define its ability**, for example in `AppServiceProvider::boot()`:

```php
Gate::define('access-admin', fn (User $user): bool => $user->is_admin);
```

Running the installer again is safe: identical files are reported as already installed. See [docs/installation.md](docs/installation.md).

## Quick start

1. Install, then define the panel ability (above).
2. Add gcego/chroma to `resources/css/app.css`, then run `npm run build`:

   ```css
   @import "tailwindcss";
   @import "../../vendor/gcego/chroma/resources/css/gcego.css";
   ```

3. Sign in and open `/admin`.
4. Check everything: `php artisan gcego:studio:health`.

Add modules in `app/GCEGo/Modules/<Name>/Module.php` (gcego/modules), pages in `app/GCEGo/Pages` (gcego/pages) and panels in `app/GCEGo/Panels` (gcego/panels). Studio discovers nothing itself: each package keeps its own discovery.

## The Studio entry point

<!-- example:studio -->
```php
$studio = app(\Gcego\Studio\Studio::class);

return [
    $studio->panels(),                                   // panel names, from gcego/panels
    $studio->modules(),                                  // module names, from gcego/modules
    $studio->hasCapability('notifications'),             // optional package detected?
    $studio->health()->result('core.boot')?->status->value,
    $studio->bootstrap()->ok,                            // all foundation packages integrated and booted
];
```

| Method | Returns |
| --- | --- |
| `panels()`, `modules()`, `pages()` | names, from each package's manager or the registry |
| `packages()` | a `PackageStatus` per foundation and optional package: installed, version, provider loaded, registered, booted |
| `capabilities()`, `hasCapability()` | capabilities of the active GCEGO packages |
| `health(bool $production = false)` | a `HealthReport` |
| `compatibility()` | a `CompatibilityReport` (PHP, Laravel, Livewire and every GCEGO package constraint) |
| `bootstrap()` | a `BootstrapResult`: `ok`, `problems`, `kernel`, `durationMs` |
| `inspect()` | the inspector report (array) |
| `extend()` | registers a trusted extension |
| `configuration()` | the typed `StudioConfiguration` |

Studio is a container singleton that holds no request state. Constructing it runs no query.

## Configuration

Publish with `php artisan vendor:publish --tag=gcego-studio-config` (the installer does this). Every value is validated when first used, and an invalid value throws `StudioConfigurationException` naming the setting.

| Key | Default | Meaning |
| --- | --- | --- |
| `enabled` | `true` | `false` makes Studio inert (no defaults, no extensions) |
| `discovery.panels` / `modules` / `pages` | `true` | `false` removes that kind's registry discovery roots |
| `appearance.default_layout` | `null` | `sidebar`, `topbar` or `compact`; feeds `gcego-panels.appearance.default_layout` |
| `appearance.default_theme` | `null` | a gcego/chroma theme; feeds `gcego-panels.appearance.default_theme` |
| `assets.vite` | `null` | Vite entries for every panel page; feeds `gcego-panels.assets.vite` |
| `assets.css` / `js` / `manifest` | app paths | files the asset checks read (never written) |
| `security.require_authentication` / `require_authorization` | `true` | how strict the health checks are; they never loosen access |
| `diagnostics.production_details` | `false` | show paths and middleware in production diagnostics |
| `extensions` | `[]` | trusted `StudioExtension` classes |
| `install.panel` / `install.ability` | `admin` / `access-admin` | what `gcego:install` creates |

`null` keeps the package's own default, and a panel's own setting (for example `Panel::Layout`) always wins. See [docs/configuration.md](docs/configuration.md).

## Health and production readiness

```bash
php artisan gcego:studio:health                # development checks
php artisan gcego:studio:health --production   # plus production readiness
php artisan gcego:studio:health --json
```

The command exits with 1 if any check fails, so it can gate CI or deployments. Every check is read-only; a check that throws is reported as a failure and never stops the others.

| Check | Verifies |
| --- | --- |
| `platform.compatibility` | PHP, Laravel and Livewire versions; every installed GCEGO package's constraints |
| `packages.integrated` | the 18 foundation packages are installed, discovered, registered and booted |
| `core.boot` | gcego/core's own diagnostics |
| `configuration.valid` | each package's typed configuration builds |
| `registry.index` | definitions load; invalid ones are counted; a stale cache is reported |
| `routes.panels` | no duplicate panel paths, no application route shadowing a panel, every panel route authenticated |
| `security.authentication` | the guards exist, the user provider's model exists, a `login` route exists |
| `panels.access` | every panel declares an access rule; module and page inspector errors |
| `security.tenancy` | tenant panels have tenancy enabled; tenancy scopes models |
| `assets.integration` | Vite manifest entries and files, Tailwind v4 import, gcego/chroma import, no second Alpine |
| `storage.writable` | cache directories |
| `production.*` | `--production` only: config, route and view caches; debug off; HTTPS; session security; optimized autoloader; built assets |

See [docs/health-checks.md](docs/health-checks.md).

## Inspecting the framework

`php artisan gcego:studio:inspect [--json]` reports what Studio assembled:

- version, bootstrap result, packages and compatibility;
- effective appearance;
- panels (routes, middleware, modules, pages), modules, pages and navigation;
- definition counts, assets, extensions, and extension sections.

In production, paths, middleware and details are left out unless `diagnostics.production_details` is on. Absolute paths are always shortened relative to the application.

## Extensions

A trusted extension (your code or a vetted package) can contribute health checks, installation steps, panel extensions (gcego/panels `PanelExtension`) and inspector sections:

<!-- example:extension -->
```php
use Gcego\Studio\Contracts\StudioExtension;
use Gcego\Studio\Extensions\StudioExtensionContext;

return new class implements StudioExtension
{
    public function id(): string
    {
        return 'acme/reports';
    }

    public function priority(): int
    {
        return 100;   // lower runs first; ties sort by id
    }

    public function register(StudioExtensionContext $context): void
    {
        $context->inspectorSection('reports', fn (): array => ['enabled' => true]);
    }
};
```

Register it in `gcego-studio.extensions`, or call `app(Studio::class)->extend(ReportsExtension::class)` from a service provider's `register()` or `boot()`. Extensions run once, after the application has booted and before panel routes are built.

- Ids must be unique, and two classes claiming one id are refused; the same class registered twice counts once.
- An extension registered after Studio applied extensions is refused.

A health check implements `StudioHealthCheck`:

<!-- example:health-check -->
```php
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;

return new class implements StudioHealthCheck
{
    public function id(): string
    {
        return 'acme.reports';
    }

    public function production(): bool
    {
        return false;   // true: only with --production
    }

    public function run(): HealthCheckResult
    {
        return HealthCheckResult::pass($this->id(), 'Reports', 'Reports are ready.', 'acme/reports');
    }
};
```

See [docs/extensions.md](docs/extensions.md).

## Security

- Studio never grants access. Panels are authenticated and deny-by-default (gcego/panels), and the starter panel needs a Gate ability that you must define.
- No configuration value disables authentication, authorization or tenant isolation.
- With gcego/sentinel installed, every panel route also checks the account state (`sentinel.active`): suspended or disabled accounts are signed out mid-session.
- Studio adds no web routes. Extension panel routes run inside the panel's middleware (`web`, `auth`, tenant, `gcego.panel`).
- The installer writes only inside the application, never through symbolic links, and overwrites only with `--force` or your confirmation.
- No query runs while the framework boots.

See [docs/security.md](docs/security.md).

## Caching and deployment

Studio works with `config:cache`, `route:cache`, `view:cache` and `optimize`. Panel routes are compiled into the route cache, and Studio's defaults are part of the cached configuration. A deploy script:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize
php artisan gcego:registry-cache
php artisan gcego:studio:health --production
```

See [docs/deployment.md](docs/deployment.md) and [docs/upgrading.md](docs/upgrading.md).

## Testing

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

The suite covers:

- configuration, extensions, packages, compatibility, health and production checks;
- the installer: fresh, re-run, conflicts, `--force`, `--dry-run`, symlinks;
- end-to-end panel navigation and CRUD;
- guest, unauthorized and cross-tenant denial;
- Livewire re-authorization, multiple panels, cached routes and configuration, and redaction.

## License

MIT. See [LICENSE.md](LICENSE.md).
