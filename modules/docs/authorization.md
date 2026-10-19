# Authorization

Every generated page requires everything in this list:

1. the module's requirement nodes;
2. the resource's (or relation's) requirement nodes;
3. the operation's permission from `Permissions`;
4. with `gcego-modules.security.authorize_by_default` (default `true`):

   | Page | Requirement |
   | --- | --- |
   | list | `Ability('viewAny', Model)` |
   | create | `Ability('create', Model)` |
   | view, edit | `Authenticated`; gcego/pages authorizes `view` or `update` on the record itself |

5. relation pages also need `ParentAccess`.

Models without a policy are denied by gcego/security: a resource never grants CRUD on its own. With `authorize_by_default` off, only 1–3 apply, and pages without any requirement follow `gcego-pages.authorization.default` (deny).

Actions are authorized by gcego/actions on every run, with their own abilities (`delete`, `restore`, `forceDelete`, custom). Forms write declared, writable fields only. Every check runs again on each Livewire update: revoked permissions, tenant switches and deleted records take effect immediately. A visible link or an existing route never grants anything.

## Tenancy

Records come from gcego/data Sources, so the tenant scope of tenant-owned models applies to lists, lookups, actions and relationships. Another tenant's record or parent is a 404. Created records get the tenant from gcego/security, never from input.
