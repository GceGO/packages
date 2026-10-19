# Livewire

## The blueprint host

```blade
<livewire:gcego-blueprint blueprint="blueprint:subjects" />
<livewire:gcego-blueprint blueprint="subjects" />
```

`Gcego\Runtime\Livewire\BlueprintHost` is registered under the configured alias
(`gcego-runtime.livewire.alias`, default `gcego-blueprint`) when
`gcego-runtime.livewire.enabled` is true.

### What it does

| Hook | Behaviour |
| --- | --- |
| `mount($blueprint)` | `Runtime::hostedBlueprint()`: validate the identifier, check that the kind is `blueprint`, authorize on the descriptor, load and validate; store the canonical identity and initial state |
| `hydrate()` | resolve and authorize again (every request); sanitize the state map against the definition |
| `updating($property, $value)` | reject anything that is not `state.<bucket>.<field>` of a declared field, or that the component's `acceptState()` refuses |
| `updated(...)` | store the normalized value |
| `render()` | `Runtime::render($definition, mode: Livewire, identity: ..., state: ...)` |

### Browser-visible state

| Property | Content |
| --- | --- |
| `blueprint` | the canonical identity, `#[Locked]` (changing it from the client throws) |
| `state` | `bucket => field => value` for stateful occurrences (untrusted; validated) |

The blueprint object, renderers and services are never serialized. A test
confirms the snapshot contains only these two properties and no definition
content.

## Rendering inside the host

Renderers see `RenderMode::Livewire` and may bind state:

```blade
@if ($context->isLivewire())
    <input type="search" wire:model.live="{{ $context->wireModel('query') }}">
@endif
```

Livewire's own hydration, dirty-checking and morphing are used unchanged. The
host adds nothing beyond the checks above. Its root element is
`<div data-gcego-host="blueprint:subjects">`.

## Errors and status codes

| Situation | Livewire request | Initial page render |
| --- | --- | --- |
| user not authorized | 403 | the mount fails (Laravel wraps it in a `ViewException`, so a 500). Protect the page route with the same Gate to return a proper 403. |
| unknown or invalid identity, wrong kind | `DefinitionNotFoundException` / `RenderException` | same, wrapped |
| invalid state update | `InvalidStateException` | n/a |

## Extension points for future packages

- **Stateful parts** (filters, form inputs, pagination) implement
  `StatefulComponent` and bind `wireModel()` in their views.
- **Actions** (gcego/actions) will need server-side methods. They must be
  added to a host deliberately, with their own authorization, never exposed by
  resolving class names from the client.
