# Tenant isolation

`GcegoTest::tenant($key)` looks at the system from inside one tenant. `actingAs($user)` signs the user in and enters the tenant through `gcego/security`, which verifies the membership. The isolation assertions then try another tenant's identifiers on the production paths:

<!-- example:tenancy -->
```php
use Gcego\Data\Source;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

GcegoTest::tenant(1)
    ->actingAs($admin)
    ->assertCurrent()
    ->assertCanAccessTenant()
    ->assertCannotAccessTenant(2)
    ->assertSees(new Source(Subject::class), [$algebra, $draft])
    ->assertCannotSee(new Source(Subject::class), [$southern])
    ->assertActionRefused('subjects', 'delete', $southern->id)
    ->assertSelectionRefused('subjects', 'bulk-delete', [$southern->id]);
```

| Assertion | Proves |
| --- | --- |
| `assertCannotAccessTenant($key)` | entering another tenant is refused (`TenantAccessException`); the current tenant is unchanged |
| `assertCannotSee($source, $keys)` | the records are neither listed nor found by key |
| `assertActionRefused($definition, $action, $key)` | the action on a foreign record is not found or denied |
| `assertSelectionRefused($definition, $action, $keys)` | a selection of foreign records processes nothing |
| `assertRestoresContext($callback)` | a job, listener or command leaves the user and tenant as they were |
| `assertCleared()` | no tenant is selected (after `reset()` or a new request) |

`run($user, $callback)` runs code as the user in the tenant and restores the previous context, even when the callback throws. It uses `SecurityManager::actingAs()`.

## Other layers

The other testables carry the tenant through `actingAs($user, $tenant)`:

- `table()->query()->assertMissing([...])`
- `infolist()->assertRecordNotFound()`
- `data()->assertNotFound()`
- `inbox($user, $tenant)->assertMissing()`
- `media()->assertNotFound()`
- `workflow()->assertRefused(..., 'not_found')`
- `panel()->assertInaccessible()`

## When isolation is missing

When a model is not mapped in `gcego-security.tenancy.models` and its source has no other scope, `assertCannotSee()` fails like this:

```text
GCEGO TENANT ASSERTION FAILED

Expected these records to be invisible in this tenant.

Tenant: 1
...
Actual:
  leaked:
    - 3

Suggestion:
  Map the model to its tenant column in gcego-security.tenancy.models, or scope its Source.
```

## Tenant fixtures

Tenants are whatever your `TenantResolver` says they are, so the toolkit cannot create them itself. Implement `Gcego\Testing\Contracts\TenantFixtureProvider` and set it in `gcego-testing.fixtures.tenants`. See [fixtures.md](fixtures.md).
