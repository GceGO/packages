# Architecture

## Authentication versus authorization

- **Authentication:** Laravel's guards (and gcego/sentinel, where installed)
  decide *who* the user is: sign-in, password resets, two-factor and email
  verification.
- **Authorization:** gcego/security decides *what that user may do*. It reads
  the identity from the configured guard on every check and never
  authenticates anyone.

`VerifiedEmail` and `PasswordConfirmed` reuse Laravel's and gcego/sentinel's own
state: `MustVerifyEmail` and the `auth.password_confirmed_at` session value.

## Flow

```
request ─► guard (identity) ─► ResolveTenant (verifies membership) ─► TenantContext (scoped)
                                                                   │
   SecurityManager / runtime / data / middleware ─► AuthorizationEngine
                                                     ├─ registered Authorizers (explicit only)
                                                     ├─ Gate::forUser($user)->inspect()  policies, abilities, app Gate rules
                                                     ├─ PermissionResolver  global: PermissionProvider · tenant: membership
                                                     └─ requirements: all must allow, none = deny
                                                   ─► AuthorizationDecision ─► AccessDeniedException (403/404)
```

## Laravel integration, not replacement

Ability checks are `Gate::forUser($user)->inspect($ability, $resource)`.
Policies, `Gate::define`, policy `Response`s (custom messages,
`denyAsNotFound()`) and the application's own `Gate::before` rules behave
exactly as in Laravel.

The package adds **no** bypass of its own. A super-administrator rule exists
only if the application registers one. Such a rule then affects Gate abilities
and global permissions, never tenant permissions or data scopes.

## Fail closed

- **Unknown abilities:** denied (`no_policy`).
- **Models without a policy:** denied (`no_policy`).
- **Unknown permissions:** denied (`unknown_permission`).
- **Definitions without requirements:** denied (`no_requirement`). Public
  access is the explicit `PublicAccess` node.
- **Guests:** denied unless a policy accepts a nullable user.
- **Tenant permissions:** denied without a selected tenant and a verified
  membership.
- **Tenant-owned data:** returns no rows without a tenant, and listing it is
  denied.
- **Configuration:** `authorization.default` other than `deny`, tenancy without
  a resolver, and invalid providers fail at boot.

## Decisions

`AuthorizationDecision` is immutable and carries:

- `allowed`, `ability` and `resource` (type and key only);
- `reason` (a `DenialReason` code) and `status`;
- a public `message()`, which is the policy's own message or a generic one;
- safe `metadata`.

`DenialStrategy::Conceal` (404) is chosen explicitly. Out-of-scope records and
foreign tenants always answer 404 so their identifiers reveal nothing; ordinary
denials answer 403.

## Lifetimes and long-running workers

| Lifetime | Services |
| --- | --- |
| singleton (registrations, configuration) | `SecurityConfig`, `AuthorizerRegistry`, `TenantResolver`, `PermissionProvider`, `AuditReporter`, `SecurityManager` (resolves the others per call) |
| scoped (reset by Laravel for every request and job, Octane included) | `TenantContext`, `DecisionCache`, `ContextFactory`, `PermissionResolver`, `AuthorizationEngine`, `RoleResolver`, `TenantAuthorizer`, `DefinitionAuthorizer`, `RecordAuthorizer`, `FieldAuthorizer`, `OperationAuthorizer` |

- **Adapters:** the adapters bound into gcego/runtime and gcego/data resolve
  scoped services on each call, so they never hold a previous request's state.
- **Decision cache:** permission decisions are memoized per request, with the
  key including guard, user, tenant and membership. A decision is never reused
  for another user or tenant. Record and policy decisions are never cached.
- **No persisted state:** no user, tenant or decision is stored in sessions,
  caches or singletons. Only the selected tenant *key* is kept in the session,
  and it is verified on every request.

## Record scoping and field security

Records are always resolved through gcego/data's scoped lookup before any
policy runs; see [authorization](authorization.md#records). Field access is
decided on the server, and writes to undeclared or read-only fields are
rejected, not silently dropped; see [authorization](authorization.md#fields).
