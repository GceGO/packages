# gcego/cli

The official GCEGO developer CLI: Artisan commands that generate GCEGO definitions, and inspect, validate and diagnose a GCEGO Admin Studio application.

The CLI owns no engine. Generators write declarative definitions that the packages already run: no controllers, Livewire components, routes or CRUD code. Inspection, validation and diagnostics call each package's own inspectors and validators. Installation stays with gcego/studio (`gcego:install`) and registry caching with gcego/registry (`gcego:registry-cache`, `gcego:registry-clear`).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Commands](#commands)
- [Quick start](#quick-start)
- [Generators](#generators)
- [Safe file generation](#safe-file-generation)
- [Inspection and validation](#inspection-and-validation)
- [Diagnostics](#diagnostics)
- [Automation, JSON and exit codes](#automation-json-and-exit-codes)
- [Extending the CLI](#extending-the-cli)
- [Security](#security)
- [Configuration](#configuration)
- Further documentation: [docs/](docs/)

## Requirements

- PHP 8.3+ (tested on 8.5), Laravel 13, Livewire 4.
- gcego/studio, which brings the eighteen foundation packages.
- Optional, each enabling its generators:
  - gcego/notifications: `make-notification`;
  - gcego/workflows: `make-workflow`;
  - gcego/settings: `make-settings`;
  - gcego/analytics: `make-dataset`, `make-metric`, `make-report`;
  - gcego/testing: `make-test`, `--test`.

## Installation

```bash
composer require --dev gcego/cli
php artisan gcego:install        # gcego/studio: configuration, starter panel
php artisan gcego:doctor
```

The commands are registered only in the console, so web requests load nothing from this package.

## Commands

| Command | Purpose |
| --- | --- |
| `gcego:about` | versions, packages, definition counts, commands |
| `gcego:doctor [--database] [--production]` | read-only framework diagnostics |
| `gcego:list [--type=]` | panels, modules, resources, pages, blueprints, widgets, layouts, navigation, workflows, settings, analytics |
| `gcego:registry [--kind=]` | registry entries, sources, roots, cache status, duplicates and failures |
| `gcego:inspect <identity>` | one definition: properties, children, validation, package inspector output |
| `gcego:validate [--type=] [--id=]` | validate definitions with each package's validators |
| `gcego:routes [--panel=] [--module=] [--middleware]` | GCEGO routes |
| `gcego:assets` | Vite manifest, Tailwind CSS v4 and gcego/chroma integration |
| `gcego:packages` | versions, constraints, registration, compatibility |
| `gcego:cache-status` | the caches GCEGO uses and how to clear each |
| `gcego:make <generator> <name>` | any generator, including custom ones (`--list`) |
| `gcego:make-blueprint`, `-module`, `-resource`, `-page`, `-panel`, `-form`, `-table`, `-infolist`, `-widget`, `-action`, `-layout`, `-navigation`, `-notification`, `-workflow`, `-settings`, `-metric`, `-dataset`, `-report`, `-test` | the generators |

Used, not redefined:

| Command | Owner |
| --- | --- |
| `gcego:install` | gcego/studio |
| `gcego:studio:health` | gcego/studio |
| `gcego:registry-cache`, `gcego:registry-clear` | gcego/registry |

Every listing and report accepts `--format=json`. Listings also accept `--limit` and `--offset`.

## Quick start

```bash
php artisan gcego:make-module Academics
php artisan gcego:make-resource Subject --model="App\Models\Subject" --module=academics
php artisan gcego:make-panel Admin --module=academics
php artisan gcego:validate
```

The resource is one declarative file, `app/GCEGo/Modules/Academics/Resources/Subjects.php`. Its table, form and infolist are derived from the model (passwords, tokens and `$hidden` attributes are left out). The Create and Edit actions are authorized by the model's policy. gcego/modules turns it into index, create, view and edit pages, and gcego/panels routes them under `/admin/subjects`.

Nobody can enter the panel until you define its ability, for example in `AppServiceProvider::boot()`:

```php
Gate::define('access-admin', fn (User $user): bool => $user->is_admin);
```

See [docs/generators.md](docs/generators.md) for every generator and its generated code.

## Generators

Each generator writes where its package discovers definitions, so no registration code is needed.

| Generator | Writes | Options |
| --- | --- | --- |
| `make-blueprint Subjects` | `app/GCEGo/Modules/Subjects/Subjects.php` (the registry's blueprint root) | `--model`, `--module`, `--title` |
| `make-module Academics` | `app/GCEGo/Modules/Academics/Module.php` | `--title`, `--description`, `--icon`, `--navigation-group`, `--panel` |
| `make-resource Subject` | `…/Academics/Resources/Subjects.php` | `--model`*, `--module`*, `--panel`, `--read-only`, `--delete` |
| `make-page Reports` | `app/GCEGo/Pages/reports.php` | `--type=page\|dashboard`, `--ability`, `--title`, `--panel` |
| `make-panel Admin` | `app/GCEGo/Panels/admin.php` | `--path`, `--domain`, `--guard`, `--tenant`, `--layout`, `--theme`, `--ability`, `--module=*`, `--page=*` |
| `make-form` / `make-table` / `make-infolist` | `…/<Module>/Forms\|Tables\|Infolists/<Name>.php` | `--module`*, `--model` |
| `make-widget StudentStats` | `app/GCEGo/Widgets/StudentStats.php` | `--type=stat\|trend\|chart\|progress\|recent\|custom`, `--model`, `--blueprint`, `--ability` |
| `make-action PublishSubject` | handler class `App\GCEGo\Support\Actions\PublishSubject`; with `--module`, an Action definition | `--ability`, `--module`, `--title` |
| `make-layout AcademicDashboard` | `app/GCEGo/Layouts/academic-dashboard.php` | `--columns`, `--ability` |
| `make-navigation AcademicNavigation` | `app/GCEGo/Navigation/academic-navigation.php` | `--item="Label=route.name"`*, `--permission` |
| `make-notification WelcomeStudent` | class `App\Notifications\WelcomeStudent` | `--channels`, `--type`, `--category`, `--title`, `--body` |
| `make-workflow ContentApproval` | `app/GCEGo/Workflows/content-approval.php` | `--model` |
| `make-settings AcademicSettings` | `app/GCEGo/Settings/academic.php` (plus a page with `--page`) | `--scope`, `--ability`, `--page` |
| `make-dataset` / `make-metric` / `make-report` | `app/GCEGo/Analytics/{Datasets,Metrics,Reports}/…` | `--model`, `--dataset`, `--aggregate`, `--field`, `--export` |
| `make-test SubjectResourceTest` | `tests/Feature/SubjectResourceTest.php` (Pest if installed, otherwise PHPUnit) | `--type`*, `--target`*, `--pest`, `--phpunit` |

\* required. In interactive mode, missing required options are asked for with defaults; with `--no-interaction` a missing option is an error that says what to pass. Every generator also accepts `--dry-run`, `--force`, `--test` and `--format=json`.

Generated code uses `declare(strict_types=1)`, real GCEGO APIs and the application's PSR-4 namespaces. Every generated definition is validated by its package in this package's test suite.

## Safe file generation

- **Plan first:** a generator builds a plan (files, operations, warnings, next steps). `--dry-run` prints it and writes nothing.
- **Conflicts:** a file that exists with the same contents is left alone (re-runs are idempotent). A file with different contents is a conflict: nothing is written and the command exits with 4, unless you pass `--force`.
- **Transactions:** all files are staged next to their targets and moved into place together. On a failure, files already moved are removed or restored and temporary files are deleted.
- **Paths:** every target is resolved inside the application. `..`, NUL bytes, paths outside the base path and symbolic links are refused.
- **Production:** generators refuse to write in production unless you confirm interactively. Read-only commands work everywhere.
- **Stubs:** customize them with `php artisan vendor:publish --tag=gcego-cli-stubs` (to `stubs/gcego/`). See [docs/stubs.md](docs/stubs.md).

## Inspection and validation

```bash
php artisan gcego:inspect module:academics
php artisan gcego:validate --type=blueprints
php artisan gcego:validate --id=blueprint:subjects --format=json
```

Validation runs a non-strict registry build, so one broken file never hides the others. It then applies each kind's validator and the module, panel, page and navigation inspectors. Problems are reported with codes:

| Code | Meaning |
| --- | --- |
| `registration.failed` | the file failed to load |
| `registration.duplicate` | a duplicate identity |
| `definition.invalid` | the component tree or kind rules failed |
| `integration.*` | a reference between definitions is broken, e.g. a panel referencing a missing module |

See [docs/validation.md](docs/validation.md).

## Diagnostics

`php artisan gcego:doctor` runs gcego/studio's health checks and the CLI's own checks:

- discovery roots, runtime renderers, styling and definitions;
- cache, queue and scheduler;
- settings, analytics and workflow definitions;
- the testing toolkit and command-name collisions;
- with `--database`, the connection and pending migrations (it reads them, never migrates).

Every check is read-only, and one that throws is reported as its own failure. See [docs/diagnostics.md](docs/diagnostics.md).

## Automation, JSON and exit codes

`--format=json` writes one JSON document with `"schema": 1`, with no colours and no other text. Exit codes:

| Code | Meaning |
| --- | --- |
| 0 | success |
| 1 | internal failure |
| 2 | invalid input (name, option, identifier, output format) |
| 3 | validation failed |
| 4 | file conflict (nothing written) |
| 5 | missing dependency (package, model, module, panel, definition) |
| 6 | diagnostic failure |

A CI step:

```bash
php artisan gcego:validate --no-interaction
php artisan gcego:doctor --no-interaction --format=json > doctor.json
```

See [docs/ci-integration.md](docs/ci-integration.md) and [docs/automation.md](docs/automation.md).

## Extending the CLI

Trusted packages register generators (run with `gcego:make <id>`), diagnostic checks, definition inspectors and stub directories, from a service provider or `config/gcego-cli.php`:

```php
$cli = app(\Gcego\Cli\CliManager::class);
$cli->generator(ReportCardGenerator::class);   // php artisan gcego:make report-card Term1
$cli->check(QueueWorkersCheck::class);          // gcego:doctor
$cli->inspector(ReportCardInspector::class);    // a section in gcego:inspect
$cli->stubs('acme/report-cards', __DIR__.'/../stubs');
```

Ids are unique. A generator id that collides with a built-in one or another package's is refused, and so is a check id that collides with a built-in check. See [docs/api-reference.md](docs/api-reference.md).

## Security

- **Authorization:** generated definitions are never public. Panels require a Gate ability, pages require authentication or `--ability`, and resources, widgets, datasets and blueprints use the model's policy. Actions require a policy ability and a confirmation, and workflow transitions require policy abilities. The CLI never defines abilities or creates users.
- **Sensitive fields:** passwords, tokens, secrets, two-factor columns and `$hidden` attributes are left out.
- **Input:** names, identifiers, classes, abilities, links, icons, paths and domains are validated. Values land in PHP only as escaped strings or checked identifiers, and stubs are rendered by placeholder replacement (nothing is evaluated).
- **Database:** generators make no database changes. Only `gcego:doctor --database` touches the database, and it only reads.
- **Output:** values of keys that look secret are printed as `[redacted]`, and paths are printed relative to the application.

See [docs/security.md](docs/security.md).

## Configuration

`php artisan vendor:publish --tag=gcego-cli-config` publishes `config/gcego-cli.php`.

| Key | Default | Meaning |
| --- | --- | --- |
| `enabled` | `true` | register the commands |
| `paths.<kind>` | discovery folder | where a kind is generated |
| `paths.support` | `app/GCEGo/Support` | handler and data-provider classes (outside every discovery folder) |
| `paths.notification` | `app/Notifications` | notification classes |
| `paths.test` | `tests/Feature` | generated tests |
| `generators.generate_tests` | `false` | always add a test |
| `generators.custom` | `[]` | custom generator classes |
| `stubs.custom_path` | `stubs/gcego` | stub overrides |
| `inspection.inspectors` | `[]` | custom inspector classes |
| `inspection.limit` | `200` | most rows a listing prints |
| `diagnostics.enabled` | `true` | allow `gcego:doctor` |
| `diagnostics.checks` | `[]` | custom check classes |

## Testing

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

## License

MIT. See [LICENSE.md](LICENSE.md).
