# Security

| Guarantee | How |
| --- | --- |
| No automatic privileges | The installer creates no users, roles or abilities. The starter panel requires a Gate ability you must define (deny by default). |
| No public panels | gcego/panels adds `auth` to every panel route and denies panels without an access rule. Studio's health check fails such panels. |
| No loosening configuration | `security.*` only changes how strict the checks are. There is no key that disables authorization, authentication or tenancy. |
| Tenant isolation | gcego/security scopes tenant models. Out-of-tenant records answer 404 (pages) or are refused (Livewire), and a tenant you do not belong to answers 404. |
| Livewire | gcego/panels' `AuthorizePanel` is persistent middleware, so every Livewire update re-checks panel access. |
| Account state | With gcego/sentinel installed, Studio adds `sentinel.active` to every panel route (`gcego-panels.routing.middleware`): an account suspended or disabled mid-session is signed out on its next panel request or Livewire update. |
| Extension routes | Panel extension routes live inside the panel's middleware group. |
| No new web surface | Studio registers no web route. Its commands are console-only. |
| Safe file writes | inside the base path only, never through symlinks, atomic, never silently overwriting |
| Diagnostics | In production, paths, middleware and details are redacted unless `diagnostics.production_details` is on. Messages never contain secrets. |
| No bootstrap queries | Verified by the test suite. |

## Your responsibilities

- Define panel abilities and model policies.
- Configure authentication (gcego/sentinel or your own), including rate limiting and two-factor where needed. With gcego/sentinel, Studio adds `sentinel.active` and `sentinel.two-factor` to every panel route.
- Serve over HTTPS with secure session cookies (`gcego:studio:health --production` reports this).
