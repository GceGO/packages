# Authorization, tenancy and security

## Visibility

Whether a node is visible is decided per request by gcego/security from
the requirements of the node and every ancestor up to the Navigation:
- `Requirement` nodes: `Permission`, `Ability`, `TenantMember`,
  `Authenticated`, `Rule`;
- the `Permission` property, which adds a `Permission` requirement:

```php
new Item(…, new Property(Item::Permission, 'institution.members.manage'))
new Group('Administration', new TenantMember, …)
new Item(…, new Ability('viewAny', Invoice::class))      // policies
new Navigation('admin', new Permission('admin.access'), …) // the whole navigation
```

**Default visibility.** A node whose chain has no requirement uses
`visibility.default`:

| Value | Visible to |
| --- | --- |
| `authenticated` (default) | signed-in users |
| `public` | everyone, guests included |
| `deny` | nobody (gcego/security's own default) |

**Evaluation:**

| Topic | Details |
| --- | --- |
| Memoization | Decisions are memoized per requirement chain and security context, so 150 items in 15 permission groups cost 15 checks. |
| Re-evaluation | They are re-evaluated on every request and every security context change (users, tenants, impersonation through `SecurityManager::actingAs()`). |

**Hiding a link is not access control.** Routes and actions must stay
protected by their own middleware, policies and gcego/security
requirements.

## Tenants

| Topic | Details |
| --- | --- |
| Context | The current tenant comes from gcego/security; there is no other tenancy engine. |
| Tenant-only nodes | `TenantMember` (or tenant permissions) hide nodes outside a tenant or for non-members. |
| Route parameters | `":tenant"` parameters use the current tenant's key. Items needing one are hidden outside a tenant. |
| Badges and records | Badge counts and record labels go through gcego/data and gcego/security, so tenant scopes apply. Cached badges are keyed per tenant and user. |

## Security

| Threat | Defence |
| --- | --- |
| Forged identities and classes | Only registered `navigation:<name>` identities render by name. Badge providers, breadcrumb providers and contributors are classes from trusted definitions, checked against their contracts. Nothing comes from the request. |
| Unsafe URLs | Paths and URLs are validated: `javascript:`, `data:`, `vbscript:`, protocol-relative URLs and quotes are refused. Route URLs come from Laravel's generator. |
| XSS | Labels, tooltips, descriptions and badge texts are escaped. Icons come from the trusted icon set (no SVG or HTML from definitions). |
| Cross-user and cross-tenant leakage | Structure is the only shared cache. Resolution and visibility are keyed by the security fingerprint. Badge cache keys hash it. |
| State | Browser state is only open groups, an open dropdown or drawer, and the compact preference (in `localStorage`). No navigation data goes into Livewire snapshots. |
| Error leakage | Badge and breadcrumb provider exceptions are reported, never shown. |
