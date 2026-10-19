# Livewire

```blade
<livewire:gcego-infolist definition="subjects" :record="$subject->id" />
```

## State

The definition and record key are the only state, and both are
`#[Locked]`. The snapshot never contains models or values.

## Every request

1. Resolve the blueprint.
2. Check its requirements.
3. Find the record through the scoped source and check `view`.
4. Decide entry access.
5. Render.

Nothing authorized earlier is reused, so permission changes and deletions
apply immediately.

## Actions and refresh

| Topic | Details |
| --- | --- |
| Record actions | gcego/actions actions with the `record` placement (Edit, Delete, Restore on trashed records, custom) appear in the header through an `ActionHost` bound to the record. |
| Edit | Uses the blueprint's gcego/forms form. |
| Confirmations | Delete asks for a signed confirmation. |
| After an action | `gcego-action-completed` re-renders the host. A deleted or no longer visible record shows the unavailable state. |
| `refresh()` | Re-reads the record. |
| Mount | A missing or out-of-scope record is a 404; a forbidden one is a 403. |

Tabs and collapsible sections are client-side (Alpine) and need no server
round trip.
