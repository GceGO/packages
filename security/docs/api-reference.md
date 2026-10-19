# API reference

All classes live under `Gcego\Security`.

## Entry point: `SecurityManager` (singleton)

Every method resolves request-scoped services when called.

| Method | Returns |
| --- | --- |
| `authorize(string $ability, mixed $resource = null, ?DenialStrategy $strategy = null)` | `AuthorizationDecision` (Gate/policy) |
| `can(string $ability, mixed $resource = null)` | `bool` |
| `permission(string $permission, bool $tenant = false)` | `AuthorizationDecision` |
| `check(Requirement ...$requirements)`, `checkFor(list $requirements, mixed $resource)` | `AuthorizationDecision` |
| `definition(ComponentInterface\|string $definition, ?string $path = null, mixed $resource = null)` | `AuthorizationDecision` |
| `bulk(ComponentInterface $definition, ?string $path, iterable $records)` | `AuthorizationDecision` |
| `resource(ResourceAccess, ResourceAbility\|string, ?Model $record = null)` | `AuthorizationDecision` |
| `record(Source\|SourceDefinition, string\|int $key, string $ability = 'view', ?DenialStrategy)` | the record, or throws `AccessDeniedException` |
| `fields(list<string\|FieldRule>, mixed $record = null)` | `FieldDecisions` |
| `context()`, `roles()` | `AuthorizationContext`, `RoleSet` |
| `actingAs(?Authenticatable, ?string $tenantKey, Closure, ?string $guard = null)` | the callback's result |
| `enterTenant(string $key)`, `switchTenant(string $key, Session)` | `TenantMembership` |
| `operation(string $ability, mixed $resource = null, ?string $definition = null, ?string $path = null)` | `SecuredOperation` |
| `runOperation(SecuredOperation, Closure)`, `authorizeDownload(SecuredOperation, string $path)` | |
| `forgetDecisions()`, `extend(Authorizer)`, `scope(ScopeProvider)`, `inspect()` | |

## Authorization

| Class | Members |
| --- | --- |
| `Authorization\AuthorizationEngine` | `ability()`, `permission()`, `requirements()`, `context()`, `strategy()`, `policyFor()`, `record()`, `session()` |
| `Authorization\AuthorizationDecision` | `static allow()`, `deny()`, `fromResponse()`; public `allowed`, `ability`, `resource`, `reason`, `status`, `metadata`; `denied()`, `message()`, `withStrategy()`, `authorize()`, `toResponse()`, `toArray()` |
| `Authorization\AuthorizationContext` | public `user`, `guard`, `tenant`, `membership`, `metadata`; `static guest()`, `static keyOf()`, `isGuest()`, `userKey()`, `fingerprint()` |
| `Authorization\ContextFactory` | `current(?guard)`, `forUser(?user)`, `run(context, Closure)`, `guard()` |
| `Authorization\DenialReason` | `Unauthenticated`, `WrongGuard`, `MissingPermission`, `UnknownPermission`, `PolicyDenied`, `NoPolicy`, `NoRequirement`, `TenantRequired`, `TenantDenied`, `NotFound`, `EmailNotVerified`, `PasswordNotConfirmed`, `RuleDenied`, `Stale` |
| `Authorization\DenialStrategy` | `Forbidden` (403), `Conceal` (404) |
| `Authorization\ResourceReference` | `type`, `key`; `static of()`, `toString()` |
| `Authorization\AuthorizerRegistry` | `authorizer()`, `scope()`, `authorizers()`, `scopes()` |

## Requirements (all implement `Contracts\Requirement`)

- **Permissions:** `Permissions\Permission(name, tenant = false)` with
  `static inTenant()`; `Permissions\Permissions(...names)` with `static any()`,
  `inTenant()`, `requiresAny()` and `isTenant()`.
- **Checks:** `Requirements\Ability(ability, ?model)`, `PublicAccess`,
  `Authenticated(?guard)`, `VerifiedEmail`, `PasswordConfirmed(seconds = 10800)`,
  `TenantMember(...roles)` and `Rule(description, Closure)`.
- **Modules:** `Resources\ResourceAccess(model, ?permission, tenant, abilities, permissions)`
  with `check(engine, context, ability, ?record)`.
- **Property syntax:** `Security(Property|Requirement ...)` with the constants
  `Permission`, `Permissions`, `AnyPermission`, `Ability`, `Model`, `Tenant`,
  `Public`, `Guard`, `Verified` and `PasswordConfirmed`.

## Contracts

| Interface | Method |
| --- | --- |
| `Contracts\Authorizer` | `decide(string $ability, mixed $resource, AuthorizationContext): ?AuthorizationDecision` |
| `Contracts\PermissionProvider` | `allows(Authenticatable, string $permission, AuthorizationContext): ?bool` |
| `Contracts\RoleProvider` | `roles(Authenticatable, AuthorizationContext): list<string>` |
| `Contracts\TenantResolver` | `find(string $key): ?Tenant`, `membership(Authenticatable, Tenant): ?TenantMembership` |
| `Contracts\ScopeProvider` | `constraints(SourceDefinition, AuthorizationContext): list<QueryConstraint>` |
| `Contracts\AuditReporter` | `report(SecurityEvent): void` |
| `Contracts\Requirement` | `evaluate(AuthorizationEngine, AuthorizationContext, mixed $resource = null): AuthorizationDecision`, `describe(): string` |

## Components

| Area | Classes |
| --- | --- |
| Permissions | `GatePermissionProvider`, `SpatiePermissionProvider`, `PermissionResolver`, `DecisionCache`, `PermissionName` |
| Roles | `RoleResolver`, `RoleSet` (`global`, `tenant`, `tenantId`), `SpatieRoleProvider` |
| Tenancy | `Tenant(type, key, model, name)`, `TenantMembership(tenant, userKey, roles, permissions)` with `grants()` and `hasRole()`, `TenantContext` (scoped), `TenantAuthorizer` (`verify()`, `enter()`, `switch()`, `leave()`), `TenantScope`, `NullTenantResolver` |
| Resources | `DefinitionAuthorizer` (`authorize()`, `identity()`, `bulk()`, `requirements()`), `ResourceAccess`, `ResourceAbility` |
| Records | `RecordAuthorizer` (`resolve()`, `inspect()`) |
| Fields | `FieldRule(field, ?read, ?write, mask)`, `FieldAuthorizer` (`decide()`, `writable()`, `visible()`), `FieldDecisions`, `FieldAccess` |
| Operations | `OperationAuthorizer` (`issue()`, `run()`, `authorizeDownload()`), `SecuredOperation` (`toArray()`, `fromArray()`, `storagePath()`, `storagePrefix()`), `TenantPath` (`for()`, `prefix()`, `contains()`) |
| Integration | `SecurityRenderAuthorizer` (runtime), `SecurityDataAuthorizer` and `SecurityDataScope` (data) |
| Middleware | `AuthorizeDefinition` (`gcego.authorize:{identity}[,{path}]`), `ResolveTenant` (`gcego.tenant[:required]`) |
| Audit | `SecurityEvent`, `SecurityEventType`, `LogAuditReporter`, `NullAuditReporter` |
| Diagnostics | `SecurityInspector::inspect(): SecurityReport` (checks `config`, `tenancy`, `integration`, `gate`, `definitions` and `definition`), `SecurityDiagnostic` |

## Exceptions (all implement `Exceptions\SecurityException`)

| Exception | Meaning |
| --- | --- |
| `AccessDeniedException` (extends Laravel's `AuthorizationException`) | a denial, rendered with its status |
| `TenantAccessException` | a tenant denial (404 by default) |
| `InvalidPermissionException` | a malformed developer declaration |
| `MissingAuthorizationContextException` | invalid configuration or inconsistent context |
