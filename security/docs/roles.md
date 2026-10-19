# Roles

Roles are resolved from the application's existing systems and kept apart by
scope:

```php
$roles = $security->roles();    // RoleSet
$roles->global;                 // platform roles from roles.provider (Spatie: getRoleNames())
$roles->tenant;                 // roles of the verified membership in the active tenant
$roles->tenantId;               // "institution:7"
```

- **Scope:** a person may be a teacher in one institution and have no
  authority in another. Tenant roles come only from that tenant's membership,
  so they are never global.
- **Not for decisions:** roles describe, they don't authorize. There is no
  "role" requirement that bypasses permissions or policies. Use
  `TenantMember('administrator')` to require a tenant role, or better, a tenant
  permission granted by it.
- **Scope providers:** custom scope providers may use `RoleSet` to narrow data
  (see `tests/Fixtures/PublishedOnlyScope.php`).
- **Changes:** role changes take effect on the next request, because
  memberships are read fresh.
