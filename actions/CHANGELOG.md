# Changelog

## Unreleased

- **Fixed:** `ActionGroup::Icon` is validated, and `Action::Icon` uses the shared Luminaries rule (`IconName::accepts`). Both take typed Luminaries icons.
- `ValidatedInput::$afterSave`: trusted callbacks of an input resolver, run with the saved record right after the handler, inside the action's transaction (gcego/forms `PersistsWithRecord` fields, gcego/media uploads).
- `ActionManager::allowsLoaded()`: display-time authorization for already-loaded rows (gcego/tables).
- `ActionHost`: `channel` and `showButtons` mount parameters and the `gcego-actions-run` request, for tables that place row and bulk actions themselves.
- `Contracts\RendersInput`: an `InputResolver` may render its own dialog input (used by gcego/forms); `ActionManager::renderInput()`. `ActionHost` shows that HTML instead of plain `Input` fields. Additive; existing resolvers are unaffected.

## 0.1.0 - 2026-10-17

First release.

- `Action` component with Property configuration and shorthand; built-ins `Create`, `Edit`, `View`, `Delete`, `Restore`, `ForceDelete`, `BulkAction`, `BulkDelete`, `BulkRestore`, and `ActionGroup`.
- Configuration nodes `Handler`, `Input`, `Confirmation`, `Transaction`, `Lifecycle`.
- `ActionExecutionEngine`: trusted resolution, scoped records, gcego/security authorization, signed single-use confirmations, validated input, Laravel transactions, structured `ActionResult`, `ActionCompleted` event.
- Bulk execution with capped, chunked, scoped selection and per-record authorization; all-or-nothing and partial policies.
- Optional idempotency keys for Idempotent actions.
- Livewire `ActionHost` with accessible confirmation and input dialogs; runtime renderers; gcego/chroma slots.
- `ActionInspector` diagnostics.
