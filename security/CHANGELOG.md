# Changelog

## 0.1.0 - 2026-10-17

First release.

- `AuthorizationEngine` over Laravel's Gate and policies, immutable `AuthorizationDecision` with 403/404 strategies.
- Requirement nodes: `Permission`, `Permissions`, `Ability`, `ResourceAccess`, `PublicAccess`, `Authenticated`, `VerifiedEmail`, `PasswordConfirmed`, `TenantMember`, `Rule`, and the Property-based `Security`.
- Permission providers for Gate abilities and spatie/laravel-permission; tenant permissions only from verified memberships.
- `TenantResolver` contract, scoped `TenantContext`, `TenantAuthorizer`, `ResolveTenant` middleware and session switching.
- `RecordAuthorizer`, `FieldAuthorizer`, `DefinitionAuthorizer` (including bulk), `OperationAuthorizer` with `SecuredOperation` and `TenantPath`.
- gcego/runtime and gcego/data integration: render authorization on every Livewire request, data authorization and mandatory scopes.
- `AuditReporter` contract with log and null reporters; `SecurityInspector` diagnostics.
