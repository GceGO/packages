# Getting started

## In a Laravel application

```bash
composer require --dev gcego/testing
```

Call `GcegoTest` in any test that boots the application (Pest feature tests, or PHPUnit tests that extend `Tests\TestCase`). Add the PHPUnit-style helpers to your base test case if you prefer them:

```php
namespace Tests;

use Gcego\Testing\Concerns\InteractsWithGcego;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithGcego;

    protected function tearDown(): void
    {
        $this->resetGcegoState();

        parent::tearDown();
    }
}
```

Database isolation stays Laravel's: use `RefreshDatabase` or `DatabaseTransactions` as you do today. The toolkit adds no migration engine.

### Pest

```php
// tests/Pest.php
Gcego\Testing\Integrations\PestIntegration::register();
```

This adds `toBeValidGcegoComponent()`, `toBeValidGcegoBlueprint()`, `toBeAccessibleMarkup()` and `toBeSuccessfulGcegoAction()`. They run the same testables as `GcegoTest`.

## In a package or extension

`Gcego\Testing\Environment\PackageTestCase` extends Orchestra Testbench (`composer require --dev orchestra/testbench`):

```php
use Gcego\Testing\Environment\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function gcegoConfiguration(): array
    {
        return [
            'auth.providers.users.model' => User::class,
            'gcego-registry.providers' => [MyDefinitions::class],
            'gcego-security.tenancy' => ['enabled' => true, 'resolver' => MyTenantResolver::class, 'models' => [Subject::class => 'institution_id']],
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

You get:

- **Providers**: every installed GCEGO provider, in dependency order (`TestApplicationFactory::providers()`). Override `gcegoProviders()` to choose fewer.
- **Configuration**: `TestApplicationFactory::defaults()`:
  - in-memory SQLite;
  - array cache (with a per-process prefix) and array session;
  - sync queue, array mailer, null broadcaster;
  - registry discovery and registry cache off.
- **Cleanup**: `GcegoTest::reset()` after every test.

Registry discovery is off by default, so the application's own `app/GCEGo` definitions are never read. Register definitions through `gcego-registry.providers`, `Registry::register()` or a [registry fixture](blueprint-testing.md#registry-fixtures).

## Without Laravel

Component, blueprint, HTML and accessibility assertions need no application. `GcegoTest` falls back to a default `TestingManager` when none is bound:

<!-- example:plain-components -->
```php
use Gcego\Components\Component;
use Gcego\Components\Property;
use Gcego\Testing\GcegoTest;

GcegoTest::component(new Component(new Property(Component::Name, 'summary')))->assertValid()->assertName('summary');
GcegoTest::html('<p>Hello</p>')->assertSeeText('Hello');
```

## Continuous integration

Nothing in the toolkit reaches an external service:

- Run the suite with `php artisan test` or `vendor/bin/pest`.
- `--parallel` is supported.
- For browser accessibility audits, install the browser in CI first: `npm ci && npx playwright install chromium`.

`GcegoTest::inspector()->inspect()->toArray()` reports the testing dependencies, database reachability, isolation risks, browser tools and package compatibility. A CI step can print it.

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| `MissingPackageException` | The assertion needs a GCEGO package that is not installed; the message names it. |
| `TenantAccessException` from `actingAs()` | The user is not a member of that tenant according to your `TenantResolver`, the same refusal a real request gets. |
| An action returns `not_found` for a record you can see | No tenant is selected (pass `$tenant` to `actingAs()`). Tenant-scoped sources fail closed without a tenant. |
| A page or panel route is missing | Routes register when the application boots. Register definitions through `gcego-registry.providers` (or files), not at runtime. |
| `assertAccessibleInBrowser()` throws | Node, Playwright or axe-core is missing. `browserAvailable()` lets the test skip. |
| Definitions from another test appear | The registry cache is on. Set `gcego-registry.cache.enabled` to false; the inspector warns about this. |
