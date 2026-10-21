# gcego/security

Authorization for GCEGO Admin Studio, built on Laravel's Gates and Policies.

It denies by default and enforces every check on the server. It provides:

- declarative requirements on definitions;
- adapters for your existing permissions and roles (Gate, Spatie);
- tenant isolation verified on every request;
- record- and field-level authorization;
- mandatory scopes for gcego/data;
- reauthorization on every Livewire interaction;
- authorization for queued operations and downloads.

**Hiding a button is not authorization.** Every protected operation is checked
again where it executes.

```php
use Gcego\Security\Permissions\Permission;

new Blueprint('subjects', new Permission('subjects.view'), new Source(Subject::class));
```

```php
use Gcego\Components\Properties\Property;
use Gcego\Security\Security;

new Security(new Property(Security::Permission, 'subjects.view'));
```

```php
use Gcego\Security\Permissions\Permissions;

new Permissions('subjects.view', 'subjects.update');
Permissions::any('reports.view', 'reports.export');
Permission::inTenant('grades.update');          // only through the active institution's membership
```

```php
$security = app(Gcego\Security\SecurityManager::class);

$decision = $security->authorize('update', $subject);   // Gate / policy: AuthorizationDecision
$decision->allowed; $decision->reason; $decision->status;
$decision->authorize();                                  // throws AccessDeniedException (403/404)

$subject = $security->record(new Source(Subject::class), $id, 'update');   // scoped lookup + policy
```

## Requirements

- PHP 8.3+ and Laravel 13.
- GCEGO packages, all ^0.1: `gcego/morfaw`, `core`, `blueprints`,
  `registry`, `runtime` and `data`.
- Optional: `spatie/laravel-permission`, adapted, never required. gcego/sentinel
  (or the application) keeps handling authentication.

## Installation

```bash
composer require gcego/security
php artisan vendor:publish --tag=gcego-security-config
```

The provider is auto-discovered. It is the `gcego/security` package of
gcego/core.

- **Authorizers:** it replaces gcego/runtime's and gcego/data's default
  authorizers. Bindings your application made itself are kept.
- **Middleware:** it registers the aliases `gcego.authorize` and `gcego.tenant`
  but applies no middleware to any route.

### What changes when you install it

| Before | After |
| --- | --- |
| Rendering asked the `gcego-runtime.render` Gate ability | Definitions are checked against their own requirement nodes. A definition with none is denied; use `PublicAccess` for public ones. |
| Data used model policies | Data uses model policies plus requirement nodes on the `Source`, tenant-owned models need a tenant, and security scopes apply to every query |

## Configuration (`config/gcego-security.php`)

| Key | Default | Meaning |
| --- | --- | --- |
| `authorization.default` | `deny` | the only accepted value |
| `authorization.guard` | `null` | guard of the actor (`null`: Laravel's default) |
| `authorization.denial_status` | `403` | or `404` to conceal everything |
| `permissions.provider` | `gate` | `gate`, `spatie`, or a `PermissionProvider` class |
| `roles.provider` | `null` | `spatie` or a `RoleProvider` class |
| `tenancy.enabled` / `tenancy.resolver` | `false` / `null` | your `TenantResolver`, required when enabled |
| `tenancy.parameter` / `tenancy.session_key` | `tenant` / `gcego.tenant` | where `ResolveTenant` reads the requested tenant |
| `tenancy.denial_status` | `404` | unknown tenants and tenants without membership |
| `tenancy.models` | `[]` | tenant-owned models (class or interface => column) |
| `fields.mask` | `********` | redacted field value |
| `audit.enabled`, `audit.channel`, `audit.denials`, `audit.sensitive` | off | security events to a log channel |

Invalid values fail at boot.

## Documentation

- [Architecture](docs/architecture.md): authentication versus authorization,
  lifetimes, fail-closed behaviour.
- [Authorization](docs/authorization.md): decisions, requirements, panels,
  modules, pages, actions, records, fields, queues.
- [Permissions](docs/permissions.md) and [roles](docs/roles.md).
- [Policies](docs/policies.md).
- [Tenancy](docs/tenancy.md).
- [Livewire](docs/livewire.md).
- [Data scoping](docs/data-scoping.md).
- [API reference](docs/api-reference.md).

## Testing

```bash
composer test      # Pest: unit, integration and HTTP tests, using SQLite, Spatie, Livewire and queued jobs
composer analyse   # PHPStan level max with Larastan
composer format    # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).
