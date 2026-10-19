# Fixtures, factories and the database

## Workspaces

<!-- example:workspace -->
```php
use Gcego\Testing\GcegoTest;

$workspace = GcegoTest::fixtures()->workspace();
$file = $workspace->php('Definitions/numbers.php', '[1, 2, 3]');

expect(require $file)->toBe([1, 2, 3]);

GcegoTest::fixtures()->cleanup();
expect($workspace->exists())->toBeFalse();
```

- **Location**: a workspace is a directory under the system temporary directory (or `gcego-testing.fixtures.directory`).
- **Naming**: the name includes the process id and Laravel's parallel `TEST_TOKEN`.
- **Safety**: paths outside the workspace (`..`, absolute paths) are refused.
- **Cleanup**: `cleanup()` (and `GcegoTest::reset()`) removes every workspace. It also runs `onCleanup()` callbacks in reverse order, even when one fails.

## Representative definitions

`GcegoTest::fixtures()->definitions()` builds valid definitions with each package's public API:

- fields and forms: `textInput()`, `select()`, `form()`;
- tables and data: `column()`, `table()`, `source($model)`;
- display: `infolist()`, `stat()`, `layout()`;
- actions and blueprints: `action()`, `resource($name, $model)` (a full CRUD blueprint);
- structure: `navigation()`, `page()`, `module($name, $model)`, `panel()`.

Use them when a test needs "a form" rather than a specific one.

## Users

`GcegoTest::fixtures()->users()->create($attributes)` tries, in order:

1. The class in `gcego-testing.fixtures.users`, which implements `UserFixtureProvider`.
2. The auth provider's model through its factory, when it uses `HasFactory`.
3. The model itself, with synthetic `name`, `email` and `password` values for the columns it has.

The n-th user is "Test User n" (`usern@example.test`, password `password`).

## Tenants

Tenants come from your `TenantResolver`, so you provide the adapter:

```php
use Gcego\Testing\Contracts\TenantFixtureProvider;
use Gcego\Testing\Fixtures\TenantFixture;

final class InstitutionFixtures implements TenantFixtureProvider
{
    public function create(array $attributes = []): TenantFixture
    {
        $institution = Institution::factory()->create($attributes);

        return new TenantFixture((string) $institution->id, $institution, $institution->name);
    }

    public function join(Authenticatable $user, TenantFixture $tenant, array $roles = [], array $permissions = []): void
    {
        $tenant->model->members()->attach($user, ['role' => $roles[0] ?? 'teacher']);
    }
}
```

```php
// config/gcego-testing.php
'fixtures' => ['tenants' => InstitutionFixtures::class],
```

```php
$tenant = GcegoTest::fixtures()->tenants()->create();
GcegoTest::fixtures()->tenants()->join($user, $tenant, ['administrator']);
GcegoTest::tenant($tenant)->actingAs($user)->assertCurrent();
```

## Database

The toolkit uses Laravel's database testing as it is:

- `RefreshDatabase` or `DatabaseTransactions`;
- factories;
- `assertDatabaseHas()`;
- SQLite in memory (`TestApplicationFactory` default), MySQL or PostgreSQL.

It never creates or runs migrations of its own.
