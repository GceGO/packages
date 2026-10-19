# Security

## Escaping

- All built-in templates print dynamic values with `{{ }}`.
- `RenderResult::text()` escapes; `trustedHtml()` is the only raw path and is
  meant for markup written in code.
- Styling classes are escaped inside `class=""`.
- Tests render `<script>` and `onerror=` payloads through headings, paragraphs,
  blueprint titles and Livewire state, and assert that they come out escaped.

## Identities, not class names

- **Valid inputs:** `Runtime` accepts only `kind:name`, `kind:scope.name` or a
  bare lowercase name. Anything with backslashes, slashes or other characters
  is a `RenderException`, before the registry is consulted.
- **Registered definitions only:** definitions are resolved only through
  gcego/registry, whose loader only executes files inside configured discovery
  roots.
- **The host renders blueprints only:** other kinds are rejected.
- **No client-controlled classes:** the client can never choose a class. The
  Livewire identity is `#[Locked]`, and state updates address declared fields
  only.

## Authorization

A registered definition is **not** permission to see it, and rendering grants
no access to its data. Data packages must authorize their own queries.

| Entry point | Authorized? |
| --- | --- |
| `<livewire:gcego-blueprint>`: mount and every later request | yes |
| `<x-gcego-runtime::render>`, `Runtime::renderAuthorized()` | yes |
| `Runtime::render()` from PHP | no: trusted application code decides |

The check is `RenderAuthorizer::authorize(DefinitionDescriptor)`. It runs on
registry metadata before the definition is loaded. The default
`GateRenderAuthorizer` calls:

```php
Gate::authorize(config('gcego-runtime.authorization.ability'), [$identity, $descriptor]);
```

**It fails closed:** Laravel denies undefined abilities, so until the
application defines `gcego-runtime.render` (or the configured ability),
nothing renders through public entry points. Guests are denied unless the
Gate closure accepts a nullable user. `gcego/security` will bind its own
`RenderAuthorizer`.

On full-page loads, also protect the route (middleware or `Gate::authorize`).
An authorization failure during the initial Livewire mount surfaces through
Blade as a `ViewException`, which is a 500, not a 403.

## State

See [state](state.md). Client state is untrusted: unknown keys are dropped on
hydrate, and updates are validated by the owning component. Objects are never
accepted.

## Errors

Messages contain component types, paths, renderer classes and view names. In
non-strict mode the placeholder hides details unless `app.debug` is on. State
exceptions name paths, never values.

## Recursion

Rendering stops at `rendering.max_depth`, 64 levels by default, and detects a
component that contains itself.
