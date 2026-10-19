# Permissions

Permission names use identifiers separated by `.` (`subjects.view`,
`admin-panel.access`, `members.*`). Invalid names throw
`InvalidPermissionException` when declared.

## Global permissions: `permissions.provider`

| Provider | Reads | `Gate::before` rules |
| --- | --- | --- |
| `gate` (default) | `Gate::forUser($user)->allows($permission)`: `Gate::define` abilities and Spatie's Gate integration | apply |
| `spatie` | `$user->hasPermissionTo($permission, $guard)` directly, with direct and role permissions | do not apply |
| a class implementing `PermissionProvider` | your infrastructure | your choice |

- **Gate provider:** with the application's `Gate::before` super-administrator
  rule, the `gate` provider grants that user every *global* permission, by the
  application's explicit rule. Choose `spatie` to evaluate stored permissions
  only.
- **Unknown permissions:** a provider returns `null`, which is a denial
  (`unknown_permission`).

No permission schema is created; existing infrastructure is adapted.

## Tenant permissions

`Permission::inTenant('grades.update')` and `Permissions(...)->inTenant()` are
answered only by the active tenant's `TenantMembership->permissions`. These
come from your `TenantResolver`, and `members.*` wildcards are allowed.

- Global roles, global permissions and `Gate::before` rules never satisfy a
  tenant permission.
- A role in one tenant grants nothing in another.

## Caching

Decisions are memoized for the current request only, keyed by guard, user,
tenant and membership.

- **Revocations:** a revoked permission takes effect on the next request.
- **Mid-request changes:** after changing permissions mid-request, call
  `$security->forgetDecisions()`.
