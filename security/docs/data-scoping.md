# Data scoping (gcego/data)

gcego/security plugs into gcego/data at two points.

## 1. `DataAuthorizer`, replaced by `SecurityDataAuthorizer`

These rules apply to every `DataManager::query()` and `find()`:

1. **Tenant:** a tenant-owned source (`tenancy.models`) requires a selected
   tenant.
2. **Requirements:** requirement nodes on the `Source` must allow:

   ```php
   new Source(Subject::class, Permission::inTenant('subjects.view'))
   ```

3. **Policy:** when the model has a policy, `viewAny` (listing) or `view` (each
   record) must allow.
4. **Neither:** with no requirements and no policy, the read is denied.

## 2. A mandatory scope on every source

`SecurityDataScope` runs inside gcego/data's constraint stage, before request
filters, search, sorting, pagination and record lookups. It applies:

- the built-in tenant scope (`tenancy.models`): only the active tenant's rows,
  and none without a tenant;
- every registered `ScopeProvider`:

  ```php
  $security->scope(new PublishedOnlyScope);   // e.g. assistants see published subjects only
  ```

```php
interface ScopeProvider
{
    /** @return list<QueryConstraint> */
    public function constraints(SourceDefinition $source, AuthorizationContext $context): array;
}
```

Each provided constraint is isolated in its own where group, and must modify
and return the builder it receives.

## Guarantees (tested)

| Attempt | Result |
| --- | --- |
| `?filter[institution]=<other tenant>` | no rows |
| `?filter[institution][in]=<own>,<other>` | only the own tenant's rows |
| search for another tenant's names | no rows |
| sorting / paging past the end / cursor pages | never another tenant's rows |
| `find($otherTenantId)` | not found (404) |
| switching tenant | only the new tenant's rows |
| `QueryContext::system()` | authorization skipped, scopes still applied |

## Acting as someone else

Scopes use the current context: the request's user and tenant.

- **Another user:** to query as another user, run inside
  `$security->actingAs($user, $tenantKey, fn () => ...)`.
- **Authorization only:** `QueryContext::for($user)` authorizes that user, with
  their own membership verified, but scope providers still see the current
  context.

Query-builder `Source::table()` sources have no model, so the tenant scope
can't recognize them. Protect them with a `ScopeProvider` that knows the table.
