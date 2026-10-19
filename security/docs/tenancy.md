# Tenancy

The framework doesn't know your tenant model. You describe tenancy with one
contract:

```php
interface TenantResolver
{
    public function find(string $key): ?Tenant;                                         // finding grants nothing
    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership; // verified, fresh
}
```

```php
// config/gcego-security.php
'tenancy' => [
    'enabled' => true,
    'resolver' => App\Security\InstitutionTenantResolver::class,
    'models' => [App\Models\Concerns\BelongsToInstitution::class => 'institution_id'],
],
```

## Example adapter for this application

This is illustrative application code, not shipped. `InstitutionAccess`
already holds the rule: a verified account in good standing, with an active
membership in an active institution.

```php
final readonly class InstitutionTenantResolver implements TenantResolver
{
    public function __construct(private InstitutionAccess $access) {}

    public function find(string $key): ?Tenant
    {
        $institution = ctype_digit($key) ? Institution::query()->find((int) $key) : null;

        return $institution === null ? null : new Tenant('institution', $institution->id, $institution, $institution->name);
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        $membership = $user instanceof User && $tenant->model instanceof Institution ? $this->access->membership($user, $tenant->model) : null;

        return $membership === null ? null : new TenantMembership(
            $tenant,
            $user->getKey(),
            [$membership->role->value],
            array_map(fn (InstitutionPermission $permission): string => $permission->value, $membership->role->permissions()),
        );
    }
}
```

The application's own `App\Support\Tenancy\TenantContext` drives its global
scopes. Set it alongside gcego's tenant, for example in the same middleware
group, so both layers agree.

## Selecting a tenant

```php
Route::middleware(['auth', 'gcego.tenant:required'])->prefix('/t/{tenant}')->group(...);   // from the URL
$security->switchTenant($key, $request->session());                                        // remember a choice
$security->enterTenant($key);                                                              // this request only
```

`ResolveTenant` reads the route parameter, then the session, and verifies the
membership for the authenticated user on **every** request. It is registered
as persistent Livewire middleware, so Livewire updates are verified too.

| Situation | Result |
| --- | --- |
| member of the tenant | tenant and membership set |
| another institution, an unknown key, a forged key | 404 (`tenancy.denial_status`) |
| suspended tenant, ended membership | 404 |
| another user's stored session choice | 404 |
| no tenant where `:required` | 403 |

## Isolation guarantees

- **Fresh state:** `TenantContext` is scoped, so every request and job starts
  without a tenant.
- **Multiple memberships:** a user with several memberships acts for one tenant
  at a time, with that tenant's roles and permissions only.
- **Data:** tenant-owned sources (`tenancy.models`) return only the active
  tenant's rows, and none without a tenant. Request filters cannot change the
  tenant.
- **Records:** records of another tenant are 404, even when their identifier is
  known.
- **System work:** `$security->actingAs(null, $key, fn () => ...)` enters a
  tenant without a membership. Scopes apply, but tenant permissions are denied.
