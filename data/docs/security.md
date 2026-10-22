# Security

## 1. Authorization fails closed

Every read authorizes before data is returned, and every write before it changes anything
(see [mutations](mutations.md) for `create`, `update`, `delete`, `restore`, `forceDelete`).

| Source | Listing | One record |
| --- | --- | --- |
| Eloquent | policy `viewAny(User)` | policy `view(User, $record)`, after the scoped lookup |
| Query builder, collection | `Gate::allows('gcego-data.query', ['viewAny', $sourceName, null])` | the same with `'view'` and the record |

- **Denied by default:** models without a policy, undefined abilities and
  guests (unless the policy accepts a nullable user).
- **Errors:** denial throws `AuthorizationException` (403), raised before any
  listing query runs.
- **No implicit permission:** a declared Source is not
  permission to read.
- **System code:** trusted code without a user (queued jobs, console commands)
  passes `QueryContext::system()`. This skips authorization only; scopes still
  apply.
- **Replacing the authorizer:** gcego/security will bind its own
  `DataAuthorizer`.

```php
Gate::policy(Subject::class, SubjectPolicy::class);          // Laravel's normal policies
Gate::define('gcego-data.query', fn (User $user, string $operation, string $source, mixed $record = null) => $user->isStaff());
```

## 2. Mandatory scopes cannot be bypassed

These layers are AND-ed, in this order:

1. **The tenant scope** of tenant-scoped sources ([tenancy](tenancy.md)).
2. **Eloquent global scopes:** soft deletes and other model scopes. They are
   never removed automatically.
3. **The source's `Constraints` queries** (`Query::make()`).
4. **Application scopes:** `Data::scope($constraint, Model::class|Interface::class)`.
5. **Per-call scopes:** `QueryContext::for($user)->constrain(...)`.
6. **The source's `Constraints` filters** (`Filter::make()`).

Request input then only adds nested AND groups:

- **Filters** can narrow, never widen.
- **Sorting and pagination** cannot change the where clauses at all.
- **An `orWhere` in a constraint** is grouped on its own (`WhereGroups`), so it
  cannot widen another constraint.
- **A constraint that swaps in a fresh builder** is rejected.

## 3. Tenant isolation

Tenant-scoped sources (`TenantScoped::make()`, or models implementing
`BelongsToTenant`) are limited to the current tenant before any other
constraint, through record lookups, relation includes and aggregates
(tenant-owned related models), search, filters, pagination and the cache.
Without a tenant every call fails closed (403). gcego/data enforces the
tenant it is given; it never decides who the tenant is. See [tenancy](tenancy.md).

## 4. Scoped record resolution

`find()`, `update()`, `delete()` and `restore()` start from the same
constrained query as listings, never from `Model::find()`:

- **Outside the scope:** a record outside it (another tenant, a fixed filter)
  is "not found" (404), indistinguishable from a missing record. The key and
  the source are never echoed.
- **Malformed keys:** a malformed key (non-numeric for an integer key, an
  invalid UUID or ULID) is "not found" without a query.

## 5. No SQL from requests

- **Names, not columns:** request input names allowlisted *keys*; columns,
  relations and operators come from the definition. Unregistered operators
  never execute.
- **Developer-declared columns:** they must match `FieldName::Pattern`
  (identifiers separated by dots) and are wrapped by the query grammar.
- **Values:** always bound parameters, cast by the filter's value type.
- **Search terms:** bound, with wildcards escaped.
- **Cursors:** validated for shape, and decoded by Laravel against the
  current ordering.
- **Tests:** injection attempts through filter keys, operators, values, sort,
  search and include are covered, and the table remains intact.

## 6. Bounded work

- **Page sizes:** capped (`pagination.max_per_page`); deep offsets refused
  (`performance.max_offset`).
- **Search:** length, word count and searched fields (20) are limited.
- **`in` lists:** limited to `filters.max_values`.
- **Sort fields:** limited to `sorting.max_fields`.
- **Includes:** allowlisted, at most 50 per request; relation depth at most 3.
- **Statement timeouts** (`performance.timeout_ms`) and collection item caps.
- **Always paginated.**

## 7. No data leaks

- **Models** serialize through `toArray()`, so `$hidden` applies;
  `FieldSecurity` removes fields per user, also from included relations.
- **Query-builder sources** have no `$hidden`: select only safe columns.
- **Closures** are trusted application code; they never reach normalized
  definitions, caches or diagnostics.
- **Diagnostics:** `explain()` returns SQL and the *number* of bindings.
  Values are included only with `includeBindings: true` **and** `app.debug`.
- **Errors:** 400 messages name parameters and keys, never submitted values.
  404, 403 (no tenant) and 503 (timeout) responses carry generic messages;
  the source, timeout and hints are in the exception's `context()` for logs.
  Configuration errors (500) are hidden by Laravel outside debug mode.
- **Cache:** isolated per user and tenant by default; tenant-scoped sources
  are never shared across tenants, whatever the isolation.

## 8. Controlled writes

Writes require `Writable` (an allowlist with validation rules), policy
authorization against the record, field write rules, and a scope re-check
after the write; see [mutations](mutations.md). Request payloads are never
mass-assigned.

## 9. Privileged contexts

`QueryContext::system()`, `forTenant()` and `acrossTenants()` are created only
by server code; no request parameter maps to them (`?authorize=0` or
`?tenant=2` are ignored). Use them in jobs and console commands, never from
values a request supplies.

## Field-level security

```php
FieldSecurity::make([
    FieldAccess::hidden('password_hash'),                          // never read or written through gcego/data
    FieldAccess::readOnly('employee_number'),                      // readable, never writable
    FieldAccess::ability('salary', read: 'viewSalary', write: 'updateSalary'),
    FieldAccess::make('diagnosis', read: fn (?User $user, ?Patient $patient) => $user?->treats($patient) ?? false),
    FieldAccess::hidden('manager.salary'),                         // a field of an included relation
]);
```

- **Reads:** an unreadable field is removed from every returned record (models
  lose the attribute, so neither serialization nor a transform can reach it).
- **No leaks through queries:** a user cannot filter or sort by a field they
  may not read (400), search skips it, and aggregating it is 403.
- **Writes:** writing a field the user may not write is 403.
- Listings evaluate rules once against the model class; record operations
  against the record. System contexts are bound only by `false` rules.
