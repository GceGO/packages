# Livewire

```blade
<livewire:gcego-actions definition="subjects" placement="header" />
<livewire:gcego-actions definition="subjects" placement="row" :record="$subject->id" />
<livewire:gcego-actions definition="subjects" :actions="['edit', 'delete']" :record="$subject->id" />
```

## State

| Property | Trust |
| --- | --- |
| `definition`, `names`, `placement`, `record` | `#[Locked]`, set on mount by server code |
| `dialog` (action, mode, token, text, fields, selection) | `#[Locked]`, set by the server |
| `feedback` | `#[Locked]` |
| `input` | **untrusted** user input, validated by the action's `InputResolver` |

## Methods

| Method | Does |
| --- | --- |
| `run(action)` | opens an input dialog (Create, Edit, actions with `Input`), opens a confirmation dialog (`ConfirmationRequired`), or executes |
| `submit()` | executes the open dialog with its token and input; validation errors appear under `input.*` |
| `cancel()` | closes the dialog |
| `runBulk(action, keys)` | starts a bulk action for untrusted keys |

- **Rendering:** each render lists only actions that are not hidden and are
  authorized for the current user (and record).
- **Execution:** every call re-resolves the definition and record, and
  re-authorizes with the current user and tenant. A membership that ended, a
  tenant switch or a revoked permission takes effect on the next interaction.
- **Locked properties:** they can't be changed from the browser.
- **Methods:** private methods aren't actions.
- **Duplicates:** buttons use `wire:loading.attr="disabled"`. Confirmations
  are single-use, and actions that must never run twice set `Idempotent` (see
  [api-reference](api-reference.md#idempotency)).

## Events

`gcego-action-completed` is dispatched with the safe `ActionResult::toArray()`.

## Dialog accessibility

The dialog has the following:

- `role="dialog"`, or `alertdialog` for destructive actions;
- `aria-modal`, and `aria-labelledby`/`aria-describedby`;
- focus moved to the first field or the confirm button;
- Escape cancels;
- labelled fields with `aria-invalid` and error descriptions;
- visible focus;
- 40 px controls.

Destructive confirmations use the danger colors.

## Hosts driven by a parent (gcego/tables)

```blade
@livewire('gcego-actions', ['definition' => 'subjects', 'channel' => $tableId, 'showButtons' => false])
```

A host with a `channel` runs `gcego-actions-run` events carrying the same
channel:
- `action` plus `record` for a row action;
- `action` plus `keys` for a bulk action.

The record key and keys are untrusted. They go through `run()` and
`runBulk()`, which re-resolve them in scope and re-authorize them.
`showButtons: false` hides the buttons, so the host only shows
confirmation and input dialogs. Requests for another channel, or to a
host without a channel, are ignored.
