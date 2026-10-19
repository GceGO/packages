# Policies

Existing Laravel policies are used as they are:

```php
Gate::policy(Subject::class, SubjectPolicy::class);

$security->authorize('viewAny', Subject::class);  // class-level
$security->authorize('update', $subject);         // record-level
```

| Policy returns | Decision |
| --- | --- |
| `true` | allowed |
| `false` | 403, `policy_denied` |
| `Response::deny('Only owners may delete subjects.')` | 403 with that message |
| `Response::denyAsNotFound()` | 404 |
| (no policy, no ability) | 403, `no_policy` |

- **Guests:** denied unless the policy method accepts `?User $user`.
- **No role bypass:** the package never bypasses a policy because of a role.
  Only an application rule such as `Gate::before` can, and `SecurityInspector`
  reports such rules.
- **Data sources:** an Eloquent `Source` whose model has a policy is authorized
  with `viewAny` (listing) and `view` (each resolved record), plus any
  requirement nodes on the source. A model with neither a policy nor
  requirements is denied.
