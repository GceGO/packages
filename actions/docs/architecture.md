# Architecture

## Definition versus execution

An action is a gcego/morfaw component (and a blueprint part with role
`action`). Constructing, validating, normalizing or registering one never
authorizes, queries or runs anything. Execution happens only through
`ActionManager::execute()`, which the Livewire `ActionHost` also uses.

## Identity and resolution

```
blueprint:subjects/action:delete      identity (stable, from the registry key and the action name)
subjects.delete                       component path (used by gcego/security's definition authorizer)
```

`ActionResolver` loads the **registered** blueprint (gcego/registry) and finds
the action by name among that blueprint's own actions, including those inside
`ActionGroup`s.

- **No reach across definitions:** an action name from a request can't reach
  another module's actions.
- **Errors:** unknown names are 404, and duplicate names in one blueprint are a
  definition error.

## Execution pipeline (`ActionExecutionEngine`)

1. **Resolve** the trusted action (404 when unknown).
2. **`prepare` hook** (read-only). Hidden or disabled actions stop here.
3. **Security context:** the user, guard and verified tenant (gcego/security).
4. **Record:** a scoped lookup through the blueprint's `Source`, using trashed
   rows only for Restore and ForceDelete. Bulk actions resolve their selection
   instead.
5. **Authorization:** `ActionAuthorizer`.
6. **Idempotency claim** (Idempotent actions only).
7. **Confirmation:** a token is issued (nothing runs) or verified and consumed.
8. **Validated, writable input:** from an `InputResolver`.
9. **Transaction:** the `beforeExecute` hook, then the handler.
10. **Commit or rollback,** then after-commit effects and the `afterSuccess` or
    `afterFailure` hooks.
11. **`ActionResult`** and the `ActionCompleted` event.

Nothing mutates before step 9. Unexpected exceptions are reported to Laravel
and answered with a generic, translated failure.

## Handlers

Handlers are declared in code only, as `new Handler(Closure|ActionHandler::class)`.
Classes are resolved from the container, so dependencies are injected.

- **Never serialized:** handlers are never in browser state or manifests.
  Normalization shows `closure` or the class name.
- **Not client-selectable:** requests can't name a handler.
- **Built-ins:** `CreateRecord`, `UpdateRecord`, `ViewRecord`, `DeleteRecord`,
  `RestoreRecord` and `ForceDeleteRecord`.

## Lifetimes

| Lifetime | Services |
| --- | --- |
| singleton | configuration, placements, resolver, input resolver registry, transaction coordinator, confirmations, idempotency, `ActionManager` (resolves the engine per call) |
| scoped (per request and job) | `ActionExecutionEngine`, `ActionAuthorizer`, `ActionInspector` |

There is no static or global execution state. `ActionContext` is built per
execution and holds no request object.

## Runtime rendering

`ActionRenderer` (registered with gcego/runtime for `Action` and
`ActionGroup`) mounts the Livewire `ActionHost` for the actions of the
registered blueprint being rendered. The record comes from the trusted
render metadata (`metadata: ['record' => $id]`).

It also works nested inside `<livewire:gcego-blueprint>`. Blueprints rendered
without a registry identity render no actions, because only registered
definitions are trusted.

## Future packages

- **gcego/forms:** registers an `InputResolver` (supports, resolve, initial)
  for actions carrying a form schema. Create and Edit then use the form's
  validation and field authorization; nothing else changes.
- **gcego/tables:**
  - renders `ActionPlacement::Row` actions per row (passing the record) and
    `Bulk` actions in its toolbar;
  - calls `ActionHost::runBulk()` with the selected keys (untrusted, scoped
    again) or exposes `window.GcegoSelection(definition)`;
  - listens to `gcego-action-completed` to refresh.
- **gcego/infolists:** handles the `View` result (`data.intent = view`).
- **gcego/notifications:** listens to `ActionCompleted`.
