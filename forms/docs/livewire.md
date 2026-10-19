# Livewire and actions

```blade
<livewire:gcego-form definition="subjects" />                                  {{-- Create --}}
<livewire:gcego-form definition="subjects" :record="$subject->id" />           {{-- Edit --}}
<livewire:gcego-form definition="subjects" action="reschedule" :record="$id" />
```

A blueprint that contains a `Form` and is rendered through gcego/runtime
mounts this host automatically.

## FormHost

| Property | Notes |
| --- | --- |
| `definition`, `action`, `record`, `step`, `confirmation`, `feedback` | `#[Locked]` |
| `data` | untrusted client state |

**Mount:**
1. authorize the blueprint (gcego/security);
2. pick `create` or `edit` when no action is given (or none, for a
   standalone form);
3. check `ActionManager::allows()`; another tenant's record and a missing
   record get the same 403;
4. hydrate.

**Methods:**

| Method | What it does |
| --- | --- |
| `addItem(path)` | adds a repeater item; respects `MaxItems` |
| `removeItem(path, key)` | removes an item; respects `MinItems` |
| `moveItem(path, key, ±1)` | moves an item; only for `Reorderable` repeaters |
| `nextStep()`, `previousStep()` | move through wizard steps; "next" validates the current step |
| `submit()` | runs `ActionManager::execute()` |

**After a submit:**
- **Validation errors:** mapped to `data.<path>`.
- **Confirmation:** handled with the actions confirmation token.
- **Success:** dispatches `gcego-action-completed`, clears passwords and
  uploads from state, resets a Create form, and follows redirects.

**Standalone forms** (no Create or Edit action): a valid submission fires the
server-side `FormSubmitted` event with the validated data and dispatches
`gcego-form-submitted`. Nothing is persisted automatically.

## Custom actions

```php
new Action('rename', 'Rename', new Property(Action::Ability, 'update'), new Property(Action::Record, true), new UsesForm, new Handler(UpdateRecord::class))
```

`UsesForm` makes the action take its input from the blueprint's form.

## ActionHost dialogs

`FormInputResolver` implements the gcego/actions `RendersInput` contract.
`<livewire:gcego-actions>` input dialogs therefore show the full form, bound
to `input.*`, with the same validation and errors.

## Front-end

| Topic | Details |
| --- | --- |
| Dependencies | Only Livewire and Alpine, both shipped with Livewire. |
| Uploads | Livewire's `WithFileUploads`, with a progress bar. |
| Accessibility | Labels, `fieldset`/`legend` for groups, `role="switch"` toggles, ARIA tabs, `aria-current="step"`, an error summary with links, `aria-live` status. |
