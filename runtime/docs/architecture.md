# Architecture

## Responsibilities

```text
gcego/registry   name → definition     ("blueprint:subjects" → Blueprint object)
gcego/runtime    component → renderer  (ExampleTable → TableRenderer → Blade view)
```

The runtime resolves definitions through the registry, validates them through
`gcego/morfaw` and `gcego/blueprints`, picks renderers, and produces Blade
output, either statically or inside a Livewire host. It does not discover
definitions, compose blueprints, query data, style components or authorize
users.

## Lifecycle of a render

```text
Runtime::render($target)
  1. resolve      string → RenderException for non-identifiers → Registry::descriptor() → Registry::get()
  2. authorize    (public entry points only) RenderAuthorizer, on the descriptor, before loading
  3. validate     assertValid(): component schema + blueprint composition (memoized by components)
  4. context      RenderContext::root(): occurrence path, identity, mode, locale, panel, theme, heading level, state
  5. render       ComponentRenderer: depth and cycle guards → RendererRegistry → renderer->render()
  6. output       lazy RenderResult; Blade views render when printed; children render where placed
```

Laravel's lifecycle is untouched: views come from the view factory, errors go
to the exception handler, and authorization uses the Gate. The runtime holds no
lifecycle state of its own. `gcego/core` only records that the package booted.

## Key types

| Type | Role |
| --- | --- |
| `Runtime` | entry point; stateless singleton |
| `RendererRegistry` | component class/interface → renderer, with precedence and override policy |
| `ComponentRenderer` | renders one occurrence; guards; error boundary; `children()` |
| `RenderContext` | immutable per-render data for one occurrence |
| `RenderResult` | lazy, `Htmlable` output with an explicit escaping policy |
| `Children` | a parent's children, rendered lazily where the template prints them |
| `BladeRenderer` | base class for view-backed renderers |
| `BlueprintRenderer`, `ContainerRenderer` | built-in renderers |
| `BlueprintHost` | the Livewire component |
| `StateCoordinator`, `StatePath` | GCEGO structure on top of Livewire state |
| `RenderAuthorizer` / `GateRenderAuthorizer` | authorization boundary |
| `StylingResolver` / `NullStylingResolver`, `StyleSlots` | styling boundary |
| `RuntimeInspector` | diagnostics |

### Decisions and deviations from the plan

- **`Runtime` absorbs `RuntimeManager`, `RenderPipeline` and `RuntimeLifecycle`.**
  The lifecycle is six ordered steps in two classes, and separate classes would
  only forward calls.
- **No `RendererResolver` contract.** `RendererRegistry` is the resolver;
  replace it in the container if needed.
- **No `Renderable` contract.** Components stay declarative and never render
  themselves.
- **No `ComponentHost`, `LivewireComponentResolver`, `ComponentState` or
  `StateStore`.** One host (`BlueprintHost`) covers registered blueprints.
  Livewire's public properties are the state store, and `StateCoordinator`
  adds only the GCEGO structure. Hosting other definition kinds can be added
  when a package needs it.
- **No Alpine.js code.** Built-in markup needs none: the error details use
  native `<details>`. Livewire ships Alpine, and the application's build owns
  any extra Alpine. Interactive behaviour belongs to feature packages.
- **Added `RendererConflictException`.** Conflicting renderer registrations
  needed a distinct, actionable error.
- **Authorization failures** are Laravel's `AuthorizationException` (HTTP 403),
  not a new exception type.
- **`testbench.yaml`** boots the package for Larastan, so namespaced package
  views are known during static analysis.

## Occurrence identity

Every occurrence has the deterministic path from `gcego/morfaw`
(`subjects.table.#0`). The context derives from it:

- `domId()`: a stable, unique HTML id;
- `statePath()`: the Livewire state bucket;
- `data-*` markers in built-in templates.

One component instance used twice gets two paths, and therefore two ids and
two state buckets.

## Long-running workers

`Runtime`, `RendererRegistry` (bindings plus memoized renderer instances),
`ComponentRenderer` and `StateCoordinator` are singletons holding only
framework-wide data. Renderers must be stateless. Contexts and results are
created per render and never stored. A test confirms no `Request` is reachable
from the shared services after handling requests, and that memory does not
grow across repeated renders.
