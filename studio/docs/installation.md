# Installation

```bash
composer require gcego/studio
php artisan gcego:install
```

Composer installs the eighteen foundation packages. Laravel package discovery loads their service providers, and gcego/core registers and boots them as GCEGO packages.

## What the installer does

1. It plans every file and compares each one with what is on disk:
   - **create**: the file does not exist;
   - **already installed**: it exists with the same contents, so nothing is written;
   - **exists, differs: kept**: it exists with other contents, and is kept unless you confirm the overwrite (interactive) or pass `--force`;
   - **overwrite (--force)**.
2. It refuses to plan if a foundation package is missing, an option is invalid, a model does not exist or a path is outside the application. In that case nothing is written.
3. It writes each file atomically (a temporary file, then a rename) and never through a symbolic link.
4. It prints the next steps.

| Target | Where |
| --- | --- |
| configuration | `config/gcego-studio.php` |
| starter panel | the first `gcego-panels.discovery.paths` folder (default `app/GCEGo/Panels`), `<panel>.php` |
| dashboard | the first registry `page` root (default `app/GCEGo/Pages`), `dashboard.php` |
| example module (`--model`) | the first `gcego-modules.discovery.paths` folder, `<Module>/Module.php` |
| extension steps | wherever the extension's `InstallationStep` says, inside the application |

## Options

```bash
php artisan gcego:install --panel=staff
php artisan gcego:install --model="App\Models\Subject" --module=Academics
php artisan gcego:install --dry-run
php artisan gcego:install --force --no-interaction
```

The example module is read-only (a table and an infolist). Add `new Create` and `new Edit` with a `Form` when you need them. Its pages are denied until the model has a policy with `viewAny` and `view`.

## What it never does

- create users, roles, permissions or Gate abilities;
- make a panel public: the starter panel requires the `install.ability` Gate ability, so nobody can enter until you define it;
- run migrations, edit `composer.json`, `package.json`, `.env`, routes or service providers;
- overwrite a file without `--force` or your confirmation.

## Customizing the stubs

```bash
php artisan vendor:publish --tag=gcego-studio-stubs
```

The installer prefers `stubs/gcego-studio/{panel,page,module}.stub`. Placeholders are `{{ name }}`, `{{ title }}`, `{{ path }}`, `{{ ability }}`, `{{ module_import }}` and `{{ module_reference }}` (panel); `{{ title }}` (page); `{{ model }}`, `{{ model_class }}`, `{{ name }}`, `{{ title }}` and `{{ key }}` (module). An unknown placeholder is an error.

## After installing

1. `Gate::define('access-admin', fn ($user) => ...)`.
2. Set up authentication: gcego/sentinel (then `php artisan migrate`) or your own `login` route.
3. `@import "../../vendor/gcego/chroma/resources/css/gcego.css";` in `resources/css/app.css`, then `npm run build`.
4. `php artisan gcego:studio:health`.
