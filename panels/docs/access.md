# Access, authentication and tenancy

## The decision

`AuthorizePanel` (`gcego.panel:<name>`) runs after authentication, verification and tenancy. It:

1. resolves the panel by its route-defined name (unknown names are 404);
2. selects the panel's guard (`Auth::shouldUse`, when the panel or the configuration names one);
3. requires a user of that guard (otherwise an `AuthenticationException`, which redirects to `login`);
4. builds the `PanelContext` (panel, user, guard, verified tenant);
5. asks `PanelAccess`, and gives a 403 (`PanelAccessDeniedException`) unless it allows;
6. publishes the context and, in tenant panels, the URL default for the tenant parameter.

`PanelAccess::decide()` returns a `PanelAccessResult`:

| Result | When | Access |
| --- | --- | --- |
| `Allowed` | every requirement and the policy allow | yes |
| `Undeclared` | neither requirements nor an `AccessPolicy` | no |
| `RequirementsDenied` | a gcego/security requirement denies | no |
| `PolicyDenied` | the policy returns anything but `true` | no |
| `Failed` | an exception while deciding | no |

## Requirements and policies

Any gcego/security requirement works:

- `Authenticated`;
- `Ability('access-admin')` (Laravel Gate);
- `TenantMember('admin')`;
- `VerifiedEmail`;
- `PasswordConfirmed`;
- `Rule` requirements.

An `AccessPolicy` is resolved from the container, so its dependencies are injected. It is called with the user and the `PanelContext`.

## Tenancy

| `Panel::Tenant` | Where the tenant comes from | Without one |
| --- | --- | --- |
| `required` | `{tenant parameter}` in the prefix, or in the domain | denied |
| `optional` | the route parameter if any, else the tenant selected in the session | the panel runs without a tenant |

gcego/security verifies membership (non-members get a 404) and scopes every query to the tenant.

## What stays enforced inside a panel

| Check | Owner |
| --- | --- |
| page requirements and default access | gcego/pages |
| model policies and record lookups | gcego/pages, gcego/security |
| resource and module permissions | gcego/modules |
| field and action authorization | gcego/forms, gcego/actions |
| tenant scopes | gcego/security |

## Guards

`Panel::Guard` selects the guard for the panel's routes and for gcego/security decisions in them. A session of another guard is not a session for the panel.

The panel switcher lists only panels that use the current guard.
