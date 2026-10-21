# gcego/testing

The testing toolkit of GCEGO Admin Studio. It gives PHPUnit and Pest tests short, explicit assertions for every GCEGO layer. Each assertion goes through the package's production services, never a stub.

```php
GcegoTest::table('subjects')->assertColumnSearchable('name');

GcegoTest::action('subjects', 'delete')
    ->actingAs($admin, 1)
    ->record($subject)
    ->confirmed()
    ->execute()
    ->assertSuccessful();

GcegoTest::tenant(1)->actingAs($admin)->assertCannotSee(new Source(Subject::class), [$foreignSubject]);
```

What it provides:

- **Fluent testables** for components, blueprints, the registry, runtime rendering, styling, data sources, security, tenancy, actions, forms, tables, infolists, widgets, layouts, navigation, pages, modules, panels, notifications, media, workflows and analytics definitions.
- **The same assertions as PHPUnit traits** (`assertGcegoComponentValid()`, …) and as optional **Pest expectations**.
- **Livewire**: `Livewire::test()` as a chosen user and tenant, plus *real* update requests through the HTTP kernel, so you can prove forged snapshots, forged tenants and revoked users are refused.
- **Fixtures**: isolated temporary workspaces, registry fixtures, representative definitions, and adapters for your own user and tenant factories.
- **Performance**: exact query counts, duplicate and N+1 detection, memory, duration and payload limits, and cache-hit checks.
- **Accessibility**: static markup rules in PHP, plus optional axe-core audits in a real browser.
- **Isolation**: a state resetter, a ready Testbench test case, and parallel-safe workspaces and cache prefixes.
- **Contracts and diagnostics**: contract checks for extension authors, package compatibility checks, and an environment inspector.

Failures say what was being tested, what was expected and what happened. Secrets are redacted:

```text
GCEGO TABLE ASSERTION FAILED

Expected table column [code] to be searchable.

Table: subjects
Assertion: assertColumnSearchable(code)

Expected:
  searchable = true

Actual:
  searchable = false
  component = Gcego\Tables\Columns\Text

Suggestion:
  Enable searching for the registered column (Column::Searchable).
```

## Contents

- [Installation](#installation)
- [Writing tests](#writing-tests)
- [Acting users and tenants](#acting-users-and-tenants)
- [What each testable covers](#what-each-testable-covers)
- [Security and tenant isolation](#security-and-tenant-isolation)
- [Livewire](#livewire)
- [Fixtures and factories](#fixtures-and-factories)
- [Performance](#performance)
- [Accessibility](#accessibility)
- [Isolation and parallel testing](#isolation-and-parallel-testing)
- [Configuration](#configuration)
- [Limitations](#limitations)

Further reading, in `docs/`:

| Guide | Covers |
| --- | --- |
| [getting-started.md](docs/getting-started.md) | Setup, test cases, CI |
| [component-testing.md](docs/component-testing.md) | Components, properties, trees, runtime, styling |
| [blueprint-testing.md](docs/blueprint-testing.md) | Blueprints, the registry, registry fixtures |
| [forms.md](docs/forms.md) | Forms |
| [tables.md](docs/tables.md) | Tables, infolists, widgets, data sources |
| [actions.md](docs/actions.md) | Actions and bulk actions |
| [security.md](docs/security.md) | Security and negative tests |
| [tenancy.md](docs/tenancy.md) | Tenant isolation |
| [livewire.md](docs/livewire.md) | Livewire |
| [interface.md](docs/interface.md) | Layouts, navigation, pages, modules, panels, notifications, media, workflows, analytics |
| [fixtures.md](docs/fixtures.md) | Fixtures, factories and the database |
| [performance.md](docs/performance.md) | Performance |
| [accessibility.md](docs/accessibility.md) | Accessibility |
| [api-reference.md](docs/api-reference.md) | Every class and method |

## Installation

Requirements: PHP 8.3+, Laravel 13, PHPUnit 12 or 13. Livewire 4, Pest 4+ and Orchestra Testbench 11 are optional. The toolkit depends only on `gcego/morfaw`. Every other GCEGO package is optional: an assertion for a package that is not installed throws `MissingPackageException`, which names the package to install.

```bash
composer require --dev gcego/testing
php artisan vendor:publish --tag=gcego-testing-config   # optional
```

`TestingServiceProvider` is auto-discovered. It registers lazy singletons and nothing else: no routes, no listeners, no migrations, no database access.

## Writing tests

In a Laravel application, call `GcegoTest` from any feature test:

<!-- example:quickstart -->
```php
use Gcego\Forms\Fields\TextInput;
use Gcego\Testing\GcegoTest;

GcegoTest::component(new TextInput('name', 'Name'))
    ->assertValid()
    ->assertName('name')
    ->assertLabel('Name');

GcegoTest::blueprint(app(Gcego\Registry\Registry::class)->blueprint('subjects'))
    ->assertValid()
    ->assertHasComponent('table')
    ->assertHasComponent('form');

GcegoTest::table('subjects')
    ->assertHasColumn('name')
    ->assertColumnSearchable('name')
    ->assertColumnSortable('name');
```

The PHPUnit style works the same way. Add `Gcego\Testing\Concerns\InteractsWithGcego` to your test case:

```php
use Gcego\Testing\Concerns\InteractsWithGcego;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithGcego;
}

// in a test
$this->actingAsGcego($teacher, $institution->id);
$this->assertGcegoComponentValid($component);
$this->assertGcegoCannot('delete', $subject);
$this->assertGcegoTenantIsolated(new Source(Subject::class), [$foreignSubject]);
```

For Pest expectations, register them once in `tests/Pest.php`. Registration is a no-op when Pest is not installed:

```php
Gcego\Testing\Integrations\PestIntegration::register();

expect($component)->toBeValidGcegoComponent();
expect($blueprint)->toBeValidGcegoBlueprint();
expect($html)->toBeAccessibleMarkup();
expect($actionResult)->toBeSuccessfulGcegoAction();
```

To test a package or extension, extend `Gcego\Testing\Environment\PackageTestCase`. It is an Orchestra Testbench case with every installed GCEGO provider, isolated configuration, the toolkit's assertions, and a state reset after each test. See [docs/getting-started.md](docs/getting-started.md).

## Acting users and tenants

`actingAs($user, $tenant)` works the way the GCEGO packages' own suites do:

1. It starts a new simulated request (per-request services are rebuilt).
2. It signs the user in on the guard.
3. It enters the tenant through `gcego/security`, which **verifies membership**.

A user who is not a member is refused with `TenantAccessException`, just as in a real request. `actingAs(null)` acts as a guest.

Every actor-aware testable has `actingAs()`: security, tenant, data, action, form, table, infolist, widget, layout, navigation, page, panel, media and livewire.

## What each testable covers

| Entry point | Asserts |
| --- | --- |
| `component($component)` | validity, error codes and paths, name, label, type, properties, defaults, accepted values, children, paths, duplicate ids, normalization |
| `blueprint($blueprint)` | validity (components and composition), roles, parts, name, title, stable identity |
| `registry()` | registered and discovered definitions, kinds, resolution, suggestions, duplicate and invalid registration, failures, health |
| `runtime()` | rendered HTML, renderer resolution, missing renderers, render failures, authorized rendering |
| `styling($component)` | resolved classes per slot (order-insensitive unless asked), unknown slots, theme, health |
| `data($source)` | rows, order, totals, pages, forged filter/sort refusal, record lookup in scope, denial |
| `security()` | gates and policies, requirements, 403 vs 404 and denial reasons, definitions, records, field access, write refusal, policies registered |
| `tenant($key)` | membership, cross-tenant refusal, visibility, foreign actions and selections, context restoration |
| `action($definition, $name)` | the full action pipeline: success, denial with reason, not found, validation, confirmation, redirects, bulk counts, availability |
| `form($form)` | fill/process/dehydrate, validation errors, forged fields, dropped fields, hydration, field definitions, conditional visibility |
| `table($definition)` | columns, labels, searchable/sortable/hideable, filters, actions, queries as the user, forged state, column access, exports |
| `infolist($definition)` | entries, labels, displayed values, redaction, hidden entries, records out of scope, rendered HTML |
| `widget($identity)` | authorization, values, comparisons, chart labels and datasets, items, refresh, forged state |
| `layout($layout)` | inspector findings, contained components, tabs, authorization, rendering |
| `navigation($name)` | visible, hidden and active items, URLs, badges, breadcrumbs on a given page |
| `page($name)` | registration, route names, URLs, opening (200/401/403/404), rendering |
| `module($name)` | resources, generated operations and pages, route names, URLs, relations |
| `panel($name)` | registration, path, modules, pages, tenancy, routes, access decisions |
| `notification($result)` / `inbox($user, $tenant)` | sent, queued and skipped deliveries with reasons; tenant-scoped inboxes; private channel authorization |
| `media()` | uploads accepted or refused (by reason), media abilities, tenant-scoped lookups |
| `workflow($instance)` / `workflow($definition)` | state, status, history, available transitions, refusal reasons; definition validity and reachability |
| `analytics($definition)` | metric and dataset definitions: validity, problems, kind, dependencies |
| `livewire($class, $params)` / `livewireRequests()` | `Livewire::test()` as a user; real update requests; locked properties; refused requests |
| `html($html)` / `accessibility($html)` | text, elements, attributes; static accessibility rules; axe-core in a browser |
| `performance()` | query counts, duplicates, N+1 shapes, memory, duration, payload, cache hits |
| `contracts()` | component, renderer, styling resolver, tenant resolver and permission provider contracts |
| `fixtures()`, `inspector()`, `compatibility()`, `reset()` | fixtures, environment diagnostics, package compatibility, state reset |

## Security and tenant isolation

A hidden button is not authorization. The negative assertions *try* the forbidden operation through the production path. They pass only when the server refuses it, or when a lookup returns nothing:

<!-- example:security -->
```php
use Gcego\Data\Source;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

GcegoTest::security()
    ->actingAs($student, 1)
    ->assertCan('view', $algebra)
    ->assertCannot('delete', $algebra);

GcegoTest::tenant(1)
    ->actingAs($admin)
    ->assertCannotAccessTenant(2)
    ->assertCannotSee(new Source(Subject::class), [$southern])
    ->assertActionRefused('subjects', 'delete', $southern->id)
    ->assertSelectionRefused('subjects', 'bulk-delete', [$southern->id]);

GcegoTest::action('subjects', 'publish')
    ->actingAs($editor, 1)
    ->record($draft)
    ->assertUnavailable()            // hidden from the editor…
    ->execute()
    ->assertDenied('policy_denied'); // …and refused by the server
```

Records outside the tenant must be indistinguishable from missing ones. `assertRecordHidden()` expects a 404 and `assertRecordForbidden()` a 403. See [docs/security.md](docs/security.md) and [docs/tenancy.md](docs/tenancy.md).

## Livewire

<!-- example:livewire -->
```php
use Gcego\Tables\Livewire\TableHost;
use Gcego\Testing\GcegoTest;

GcegoTest::livewire(TableHost::class, ['definition' => 'subjects'])
    ->actingAs($admin, 1)
    ->assertSee('Algebra')
    ->assertDontSee('Southern');

GcegoTest::livewire(TableHost::class, ['definition' => 'subjects'])
    ->actingAs($admin, 1)
    ->assertLocked('definition', 'blueprint:other');
```

`test()` returns Livewire's own `Testable`, so every Livewire assertion is still there. To prove a forged request is refused, take a snapshot from a real page and post it back through the HTTP kernel with `GcegoTest::livewireRequests()`. Persistent middleware runs too. See [docs/livewire.md](docs/livewire.md).

## Fixtures and factories

```php
$workspace = GcegoTest::fixtures()->workspace();             // a temporary directory, removed on reset
$user = GcegoTest::fixtures()->users()->create();             // your user model (its factory when it has one)
$tenant = GcegoTest::fixtures()->tenants()->create();         // through your TenantFixtureProvider
GcegoTest::fixtures()->tenants()->join($user, $tenant, ['teacher']);
$form = GcegoTest::fixtures()->definitions()->form();         // a representative, valid Form
```

The toolkit assumes no user or tenant schema. For tenants, implement `Gcego\Testing\Contracts\TenantFixtureProvider` once and set it in `gcego-testing.fixtures.tenants`. The toolkit never creates migrations: use Laravel's `RefreshDatabase`, `DatabaseTransactions` and factories. See [docs/fixtures.md](docs/fixtures.md).

## Performance

<!-- example:performance -->
```php
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

GcegoTest::performance()
    ->measure(fn () => Subject::query()->get())
    ->assertQueryCountAtMost(1)
    ->assertNoDuplicateQueries()
    ->assertNoRepeatedQueries();
```

Query counts are exact. The N+1 check reports query *shapes* (SQL with literals stripped) that run five or more times. It is a heuristic, not proof. Durations depend on hardware, so only assert generous limits. See [docs/performance.md](docs/performance.md).

## Accessibility

<!-- example:accessibility -->
```php
use Gcego\Testing\GcegoTest;

GcegoTest::accessibility('<label for="q">Search</label><input id="q" name="q">')->assertAccessible();
GcegoTest::accessibility('<input name="q">')->assertViolates('form-label');
```

There are 14 static rules: labels, alt text, button and link names, heading order, empty headings, table headers, dialog names, duplicate ids, ARIA references, error associations, positive tabindex, focusable content inside `aria-hidden`, and landmark names.

`assertAccessibleInBrowser()` runs axe-core in headless Chromium. It needs Node with the `playwright` and `axe-core` packages. When they are missing it throws rather than passing; use `browserAvailable()` to skip. Static checks do not replace a browser audit or manual testing. See [docs/accessibility.md](docs/accessibility.md).

## Isolation and parallel testing

- `GcegoTest::reset()` (called by `PackageTestCase` after every test) clears:
  - the signed-in user (every guard);
  - the tenant context and the security decision cache;
  - the panel context;
  - Livewire state;
  - resolved registry definitions;
  - fixture workspaces;
  - per-request services;
  - and any callbacks you add with `TestStateResetter::using()`.
- `TestApplicationFactory` configures in-memory SQLite, array cache and session, a sync queue, the array mailer, and registry discovery and caching off.
- Workspaces and the cache prefix include Laravel's `TEST_TOKEN`, so `php artisan test --parallel` and `pest --parallel` are safe.
- `GcegoTest::inspector()->inspect()` reports settings that can leak state between tests, such as a file cache, a real queue or the registry cache.

## Configuration

`config/gcego-testing.php`:

| Key | Default | Meaning |
| --- | --- | --- |
| `enabled` | `true` | Register the toolkit's services |
| `assertions.verbose_failures` | `true` | Print context, expected and actual values |
| `assertions.redact` | password, secret, token, … | fnmatch patterns of keys whose values are replaced by `[redacted]` |
| `fixtures.directory` | system temp | Where workspaces are created |
| `fixtures.users` | `null` | A `UserFixtureProvider` class |
| `fixtures.tenants` | `null` | A `TenantFixtureProvider` class |
| `performance.repeated_queries` | `5` | Repetitions the N+1 check reports |
| `accessibility.node` | `node` | Node binary for browser audits |
| `accessibility.node_modules` | `null` (search upwards) | Where `playwright` and `axe-core` are installed |
| `isolation.reset_registry` / `reset_runtime` / `reset_tenant_context` | `true` | What `reset()` clears |

## Limitations

- **No Studio or settings assertions yet.** gcego/studio and gcego/settings now exist, but the toolkit has no dedicated testables for them: test settings definitions with `GcegoTest::component()` and Studio with its own reports (`Studio::health()`, `Studio::inspect()`).
- **Analytics: definitions only.** `GcegoTest::analytics()` covers the definition API (`validate()`, `problems()`, `kind()`, `dependencies()`); report execution and dataset, export and cache authorization have no assertions yet (test them through `AnalyticsManager`).
- Static accessibility rules check markup only. Contrast, focus behaviour and screen-reader output need the browser audit and manual testing.
- The N+1 check is a heuristic.
- `RegistryFixture::apply()` rebuilds the registry services. Services resolved earlier keep the previous registry, so apply it before using them.

## Testing this package

```bash
composer test                  # Pest
vendor/bin/pest --parallel     # parallel
composer analyse               # PHPStan, level max
composer format                # Pint
```

The suite runs against every installed GCEGO package through the toolkit's own `PackageTestCase`. The examples marked in this README and in `docs/` are executed by `tests/Unit/DocumentedExamplesTest.php`.
