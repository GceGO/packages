# Livewire

## One host

Page routes render `PageHost` (`gcego-page`) inside the layout:

```blade
<livewire:gcego-page page="page:subjects-view" record="7" />
```

| Property | Locked | Content |
| --- | --- | --- |
| `page` | yes | the page identity |
| `record` | yes | the record key (record pages) |
| `parameters` | yes | validated route parameters |
| `tab` | yes | the tab opened first |
| `missing` | yes | the record disappeared |

That is the whole snapshot (about 460 bytes for a view page): no model, class name, decision or rendered data.

## Every request

`mount()` opens the page (or reuses the page route's open page in the same request). `hydrate()` opens it again with `interaction: true` on every update: registry, page authorization, scoped record lookup, record ability. A denial throws (403); a record that disappeared shows the missing state.

## Children

Tables, forms, infolists, actions and interactive widgets are separate Livewire components mounted during the page render with stable keys (`gcego-table-<hash of identity and path>`, …). Updating them never re-renders the page, and re-rendering the page renders them as placeholders, keeping their state.

## Events

`gcego-action-completed` from any of the page's hosts triggers `PageHost::actionCompleted()`, which redirects (optionally with `wire:navigate`, `rendering.wire_navigate`) or re-renders the header (a renamed record gets its new title). See [actions](actions.md).

## Testing

Use `Livewire::test(PageHost::class, ['page' => 'subjects.view', 'record' => 1])`, or a real GET of the page followed by a POST to Livewire's update endpoint to test reauthorization across requests.
