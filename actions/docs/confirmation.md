# Confirmation

Defaults (`config/gcego-actions.php`):

| Type | Confirmation |
| --- | --- |
| Delete, ForceDelete, BulkDelete | required |
| bulk actions | required (`confirmation.bulk`) |
| Restore, BulkRestore | `confirmation.restore` (false) |
| others | only with `Property(Action::Confirmation, true)` or a `Confirmation` node |

## Flow

1. Execute without a token. The action is authorized, then the result is
   `ConfirmationRequired` with `confirmation`: the token, title, message,
   labels and `destructive`. **Nothing runs.**
2. Execute again with `confirmation: $token`. Everything is authorized again,
   the token is verified and consumed, and the action runs.

## Token integrity

- **Bindings:** the token is an HMAC (derived from `app.key`) over the action
  identity, the record key or a hash of the exact selection, the user, guard,
  tenant, expiry (`confirmation.ttl`, 600 seconds) and a nonce.
- **Single use:** its nonce is consumed with an atomic cache `add()`, so a
  replay or a double submit is refused (`Conflict`, `confirmation_invalid`).
- **Rejected tokens:**
  - forged or altered;
  - for another record, selection, action, user or tenant;
  - expired;
  - reused;
  - a plain `"true"`.

  Each test is in `tests/Integration/SecurityTest.php`.
- **Not bound to input:** the confirmation binds the action and target, not
  submitted input values. Input is validated again on the confirmed request.
- **Cache store:** use a cache store shared by your servers with atomic `add()`
  (`cache.store`: redis, database, memcached).
