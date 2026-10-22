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

1. **Eloquent global scopes:** tenancy (`InstitutionScope`), soft deletes and
   any other model scopes. They are never removed automatically.
2. **The source's own constraints:** `Query` nodes.
3. **Application scopes:** `DataManager::scope($constraint, Model::class|Interface::class)`.
4. **Per-call scopes:** `QueryContext::for($user)->constrain(...)`.
5. **Fixed `Filter` nodes.**

Request input then only adds nested AND groups:

- **Filters** can narrow, never widen.
- **Sorting and pagination** cannot change the where clauses at all.
- **An `orWhere` in a constraint** is grouped on its own (`WhereGroups`), so it
  cannot widen another constraint.
- **A constraint that swaps in a fresh builder** is rejected.

**Multi-tenancy:** register a tenant scope once and every source of the
matching models is limited:

```php
// TenantScope is your QueryConstraint; it reads the current tenant from its own collaborators.
app(DataManager::class)->scope(app(TenantScope::class), BelongsToInstitution::class);
```

A school administrator then cannot read another school's records through
filters, search, sorting, pagination or a known key. The tests cover all of
these.

## 3. Scoped record resolution

`find()` starts from the same constrained query as listings, never from
`Model::find()`:

- **Outside the scope:** a record outside it is "not found"
  (`RecordNotFoundException`, 404), indistinguishable from a missing record.
  The key is never echoed.
- **Malformed keys:** a malformed key (non-numeric for an integer key, an
  invalid UUID or ULID) is "not found" without a query.

## 4. No SQL from requests

- **Names, not columns:** request input names allowlisted *keys*; columns,
  relations and operators come from the definition.
- **Developer-declared columns:** they must match `FieldName::Pattern`
  (identifiers separated by dots) and are wrapped by the query grammar.
- **Values:** always bound parameters, cast to the declared `ValueType`.
- **Search terms:** bound, with wildcards escaped.
- **Cursors:** validated for shape, and decoded by Laravel against the
  current ordering.
- **Tests:** injection attempts through filter keys, operators, values, sort,
  search and include are covered, and the table remains intact.

## 5. Bounded work

- **Page sizes:** capped (`pagination.max_per_page`).
- **Search:** length and word count are limited.
- **`in` lists:** limited to `filters.max_values`.
- **Sort fields:** limited to `sorting.max_fields`.
- **Includes:** at most 50 per request.
- **Always paginated:** sources are always paginated.

## 6. No data leaks

- **Models** serialize through `toArray()`, so `$hidden` applies.
  `Transform::only()` cannot select hidden attributes.
- **Query-builder sources** have no `$hidden`: select only safe columns.
- **Callbacks** are `Callback` property values or configuration-node closures.
  They never reach normalized definitions, caches or diagnostics.
- **Diagnostics:** `explain()` returns SQL and the *number* of bindings.
  Values are included only with `includeBindings: true` **and** `app.debug`.
  Nothing is logged.
- **Errors** name keys and parameters, never submitted values.

## 7. No writes

gcego/data only reads:

- no create, update or delete;
- no mass assignment of request payloads.

Mutations belong to gcego/actions and gcego/forms, which should resolve their
target with `findOrFail()`.

## Field-level security

```php
FieldAccess::hidden('password_hash');                         // never read or written through gcego/data
FieldAccess::readOnly('code');                                // readable, never writable
FieldAccess::ability('salary', read: 'viewSalary', write: 'updateSalary');
new FieldAccess('diagnosis', read: fn (?User $user, ?Patient $patient) => $user?->treats($patient) ?? false);
```

- **Reads:** an unreadable field is removed from every returned record (models
  lose the attribute, so neither serialization nor a transform can reach it).
- **No leaks through queries:** a user cannot filter or sort by a field they
  may not read (400), search skips it, and aggregating it is 403.
- **Writes:** writing a field the user may not write is 403.
- Listings evaluate rules once against the model class; record operations
  against the record. System contexts are bound only by `false` rules.

## Tenant isolation

Tenant-scoped sources are limited to the current tenant before any other
constraint, fail closed without a tenant, and stamp the tenant on create.
See [tenancy](tenancy.md).

