# Authorization

## Checks

```php
$security = app(SecurityManager::class);

$security->authorize('update', $subject);        // Gate ability / policy method
$security->can('viewAny', Subject::class);
$security->permission('reports.export');         // global permission
$security->permission('grades.update', tenant: true);
$security->check(new Permission('a.b'), new VerifiedEmail);   // all must allow
```

Every call returns an `AuthorizationDecision`. Call `->authorize()` to throw
`AccessDeniedException`, a Laravel `AuthorizationException` that renders with
the decision's status.

## Requirement nodes

Requirement nodes attach to any component: a blueprint, a data `Source`, and
the panels, pages and actions of future packages.

| Node | Allows when |
| --- | --- |
| `new Permission('subjects.view')` | the global permission is held |
| `Permission::inTenant('grades.update')` | the active tenant's membership grants it |
| `new Permissions(...)`, `Permissions::any(...)`, `->inTenant()` | all of them, or any of them |
| `new Ability('viewAny', Subject::class)`, `new Ability('update')` | the Gate ability or policy method allows, for the class or the record being checked |
| `new ResourceAccess(Subject::class, permission: 'subjects')` | module conventions (see below) |
| `new PublicAccess` | always, guests included (explicit) |
| `new Authenticated('admin')` | a user, optionally on that guard |
| `new VerifiedEmail` | Laravel's `MustVerifyEmail` rule |
| `new PasswordConfirmed(900)` | password confirmed within 900 seconds |
| `new TenantMember('administrator')` | a verified membership, optionally with that tenant role |
| `new Rule('2FA on', fn (AuthorizationContext $c) => ...)` | the trusted closure returns `true` |
| `new Security(new Property(Security::Permission, ...), ...)` | the Property-syntax equivalent |

`Security` property keys: `Permission`, `Permissions`, `AnyPermission`,
`Ability` (with `Model`), `Tenant`, `Public`, `Guard`, `Verified` and
`PasswordConfirmed`.

## Definitions: panels, modules, pages, actions

The requirements of the target component **and every ancestor** must allow.

```php
$security->definition('blueprint:subjects');                        // registered identity
$security->definition($blueprint, 'subjects.#2', $record);          // a nested component (an action)
$security->bulk($blueprint, 'subjects.#2', $records);               // every record must allow
```

Routes:

```php
Route::get('/admin/subjects', ...)->middleware('gcego.authorize:blueprint:subjects');
```

The identity comes from the route definition, never from the request. Unknown
identities answer 404, and definitions without requirements answer 403.

Future packages declare requirements on their own definitions and call these
APIs:

- **gcego/panels:** a `Permission` on the panel, plus `gcego.authorize` on its
  routes.
- **gcego/actions:** `definition($root, $actionPath, $record)` before execution,
  and `bulk()` for bulk actions.

Visibility in the UI never replaces these checks.

## Modules: `ResourceAccess`

```php
$access = new ResourceAccess(Subject::class, permission: 'subjects', tenant: true, permissions: ['viewAny' => 'subjects.view']);

$security->resource($access, ResourceAbility::Update, $subject);
```

| Ability | Policy method | Conventional permission |
| --- | --- | --- |
| `ViewAny` | `viewAny` | `subjects.view_any` |
| `View`, `Update`, `Delete`, `Restore` | same name | `subjects.view`, ... |
| `ForceDelete` | `forceDelete` | `subjects.force_delete` |
| `Create` | `create` | `subjects.create` |

- **Checks:** the policy (when the model has one) and the permission (when a
  prefix or override is configured) must **both** allow. With neither, the
  ability is denied.
- **Overrides:** use `abilities:` to rename a policy method and `permissions:`
  to rename a permission.
- **Records:** record abilities require the record.
- **As a requirement:** a `ResourceAccess` node on a blueprint protects the
  module with `viewAny`.

## Records

```php
$subject = $security->record(new Source(Subject::class), $id, 'update');
$subject = $security->record($source, $id, 'view', DenialStrategy::Conceal);
```

1. **Scoped lookup** through gcego/data. Global scopes, soft deletes, the
   source's constraints and the security scopes all apply. Out of scope means
   missing: **404**.
2. **The source's requirement nodes**, evaluated with the record.
3. **The policy** (or Gate ability), applied to the record. A denial answers
   **403**, or 404 with `Conceal`.

The record is never loaded through an unrestricted `Model::find()`. Keys of any
shape work (integer, UUID, ULID, custom string keys), and malformed keys are
404 without a query.

## Fields

```php
$fields = $security->fields([
    'name',
    new FieldRule('national_id', read: new Permission('students.identity.view'), mask: true),
    new FieldRule('score', write: Permission::inTenant('grades.update')),
], $student);

$fields->access('score');                         // FieldAccess::Readable
$data = app(FieldAuthorizer::class)->writable($request->all(), $fields);   // throws on read-only or undeclared keys
$out = app(FieldAuthorizer::class)->visible($student, $fields);            // declared readable fields; masked; hidden removed
```

`FieldAccess` levels, lowest first: `Hidden`, `Redacted`, `Readable`,
`Editable`.

- **No silent trimming:** `writable()` rejects the request (403) instead of
  trimming it, so a field hidden in a form can never be written through a
  crafted request.
- **Model serialization:** `visible()` reads models through `toArray()`, so
  `$hidden` attributes never leave the server.

## Queued operations, exports and downloads

```php
$operation = $security->operation('export', $subject);           // checked now
ExportSubject::dispatch($operation->toArray());

// in the job
$security->runOperation(SecuredOperation::fromArray($payload), function (AuthorizationContext $context, ?Subject $subject) use ($operation) {
    Storage::put($operation->storagePath('subject.csv'), ...);
});

// on download
$security->authorizeDownload($operation, $path);
```

- **`runOperation()`:** at execution it reloads the user through the guard's
  provider, re-verifies the tenant membership, reloads the record inside the
  tenant, and repeats the check. If the permission was revoked, the membership
  ended, the user was deleted or the payload was tampered with, the job stops.
- **Downloads:** they require the same user, guard and tenant, a path inside
  `storagePrefix()`, and the check still allowing. Anything else answers 404.
- **Storage paths:** `TenantPath` keeps tenants' files apart
  (`gcego/tenants/{type}/{key}/...`) and rejects `..` and absolute paths.

## Audit

With `audit.enabled`, the following go to the `AuditReporter`
(`LogAuditReporter` writes to `audit.channel`):

- denials;
- tenant denials;
- sensitive grants (`audit.sensitive` patterns);
- revoked operations.

Events carry only codes and identifiers: ability, reason, status, resource
type and key, guard, user key and tenant id. They never carry names,
attributes, payloads or credentials. Bind your own `AuditReporter`, for
example an adapter to your application's audit log.
