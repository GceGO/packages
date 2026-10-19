# API reference

All classes live under `Gcego\Actions`.

## `ActionManager` (singleton)

| Method | Returns |
| --- | --- |
| `execute(ActionRequest)` | `ActionResult` |
| `executeOrFail(ActionRequest)` | `ActionResult`, or throws `ActionExecutionException` |
| `resolve(string $definition, string $action)` | `ResolvedAction` (or throws `ActionNotFoundException`) |
| `actions(string $definition, ?string $placement = null)` | `list<ResolvedAction>` |
| `available(string $definition, ?string $placement = null, string\|int\|null $record = null, array $names = [])` | visible and authorized actions (display only) |
| `allows(ResolvedAction, $record = null)`, `takesInput(ResolvedAction)`, `initialInput(ResolvedAction, $record = null)` | |
| `inputResolver(InputResolver)` | register (gcego/forms) |
| `allowsLoaded(ResolvedAction, mixed $record): bool` | display-time check for a row already loaded through the scoped source (gcego/tables); bulk actions use their per-record ability; execution re-checks by key |
| `renderInput(ResolvedAction, string\|int\|null $record, array $state, array $errors, string $prefix = 'input'): ?string` | dialog HTML from a `RendersInput` resolver, or `null` |
| `placements()` | `ActionPlacement` (`register()`, `has()`, `all()`) |
| `inspect(string $definition)` | `ActionReport` |

## Definitions

`Action`, `Create`, `Edit`, `View`, `Delete`, `Restore`, `ForceDelete`,
`BulkAction` (adds `Policy`), `BulkDelete`, `BulkRestore`, `ActionGroup`,
`ActionIntent` and `ActionPlacement`. See
[action definitions](action-definitions.md).

## Execution

| Class | Members |
| --- | --- |
| `Execution\ActionRequest` | `definition`, `action`, `record`, `selection`, `input`, `confirmation`, `idempotencyKey`; `withConfirmation()` |
| `Execution\ActionResult` | `status` (`ActionStatus`), `action`, `message`, `errors`, `recordType`, `recordKey`, `redirect`, `refresh`, `closeDialog`, `confirmation`, `data`, `reason`; `successful()`, `httpStatus()`, `toArray()` |
| `Execution\ActionStatus` | `Success`, `ValidationFailed` (422), `Denied` (403), `ConfirmationRequired` (409), `NotFound` (404), `Conflict` (409), `Failed` (500) |
| `Execution\ActionContext` | see [custom actions](custom-actions.md) |
| `Execution\ValidatedInput` | `get()`, `all()`, `isEmpty()` |
| `Execution\ActionEffects` | `redirect()`, `message()`, `withoutRefresh()`, `keepDialogOpen()`, `afterCommit()` |
| `Execution\ActionCompleted` | event: `result`, `userKey` |
| `Execution\TransactionCoordinator` | `run()`, `afterCommit()` |
| `Execution\IdempotencyGuard` | `claim()`, `release()` |

## Contracts

| Interface | Methods |
| --- | --- |
| `Contracts\ActionHandler` | `handle(ActionContext): mixed` |
| `Contracts\ActionAuthorizer` | `authorize(ResolvedAction, AuthorizationContext, mixed $record = null): AuthorizationDecision` |
| `Contracts\RendersInput` | `renderInput(ResolvedAction, string\|int\|null $record, array $state, array $errors, string $prefix): string`. Optional for resolvers; controls bind to `<prefix>.<field>` |
| `Contracts\InputResolver` | `supports(ResolvedAction): bool`, `resolve(ResolvedAction, ActionContext, array $submitted): ValidatedInput`, `initial(ResolvedAction, ActionContext): array` |

## Idempotency

`Property(Action::Idempotent, true)` requires `ActionRequest::$idempotencyKey`
(8 to 128 characters).

- **Duplicates:** the first request claims the key (an atomic cache `add`,
  scoped to action and user, for `idempotency.ttl` seconds). Repeats are
  `Conflict`/`duplicate`.
- **Failures:** a failed run releases the key, so the caller can retry.
- **Guarantee:** this is at-most-once per key within the claim lifetime, as
  atomic as the cache store. It is **not** exactly-once delivery. A crash after
  commit but before the response leaves the key claimed.

## Resolution, input, bulk, confirmation

- **Resolution:** `Resolution\ActionResolver` (`resolve()`, `actions()`,
  `blueprint()`, `static collect()`), and `Resolution\ResolvedAction`
  (`identity()`, `name()`, `model()`, `isBulk()`, `requiresConfirmation()`,
  `usesTransaction()`, `transactionConnection()`, `recordSource()`).
- **Input:** `Input\RulesInputResolver` and `Input\InputResolvers`.
- **Bulk:** `Bulk\SelectionResolver` (`validate()`, `chunks()`, `resolve()`)
  and `Bulk\BulkPolicy` (`AllOrNothing`, `Partial`).
- **Confirmation:** `Confirmation\ConfirmationManager` (`issue()`,
  `consume()`, `static selectionHash()`).

## Rendering, Livewire, diagnostics

- **Rendering:** `Rendering\ActionRenderer` (gcego/runtime) and the view
  `gcego-actions::host`.
- **Livewire:** `Livewire\ActionHost` (`gcego-actions`).
- **Diagnostics:** `Diagnostics\ActionInspector::inspect()` returns an
  `ActionReport` (`actions`, `diagnostics`, `isHealthy()`, `errors()`,
  `toArray()`). Its checks are `definition`, `handler`, `authorization`,
  `input`, `source`, `soft-deletes` and `action`.

## Configuration (`config/gcego-actions.php`)

| Key | Default |
| --- | --- |
| `confirmation.delete`, `.force_delete`, `.bulk` | `true` |
| `confirmation.restore` | `false` |
| `confirmation.ttl` | `600` |
| `transactions.enabled` | `true` |
| `bulk.max_selection` | `500` |
| `bulk.chunk_size` | `100` (at most 100) |
| `idempotency.ttl` | `3600` |
| `cache.store` | `null` (the default store) |

## Exceptions (all implement `Exceptions\ActionException`)

| Exception | Meaning |
| --- | --- |
| `ActionNotFoundException` | 404 |
| `InvalidActionException` | a definition error |
| `ActionConfirmationException` | an invalid confirmation |
| `ActionExecutionException` | thrown by `executeOrFail()` |

Authorization failures surface as `ActionResult::denied()`.
