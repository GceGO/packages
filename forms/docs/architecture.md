# Architecture

```
Form (component tree, immutable, query-free)
  │  SchemaCompiler: once per definition, cached in a WeakMap by FormManager
  ▼
FormSchema ─ FieldEntry per state path ("name", "objectives.*.title")
  │           + live paths, dependency graph, wizard steps
  ├── StateHydrator     record/defaults ─► browser state (readable fields only)
  ├── FormManager::normalize   untrusted input ─► FormState (unknown keys = 403)
  ├── ConditionEvaluator       visible / required / disabled instances
  ├── ValidationEngine         Laravel Validator with rules from the schema only
  └── StateDehydrator          validated ─► attributes (writable, submitted only)
```

## Packages it builds on

| Package | Used for |
| --- | --- |
| gcego/morfaw | `Form`, fields and layouts are `Component`s; configuration is `Property` plus configuration nodes |
| gcego/runtime | `FieldRenderer`, `LayoutRenderer` and `FormRenderer` are registered renderers (Blade) |
| gcego/chroma | slots for every field and layout; presets in `Presets\FormPresets` |
| gcego/data | relationship options through `DataManager::query()`, as the current user |
| gcego/security | field rules (`Access`), record and definition authorization, tenancy |
| gcego/actions | Create/Edit/custom actions take their input from `FormInputResolver`; persistence stays in action handlers |

## Lifecycles

| Lifecycle | Steps |
| --- | --- |
| **Render** | `FormHost::render()` builds a `FormRenderFrame` (schema, state, access, errors, step), pushes it on the scoped `FormRenderContext`, and renders the `Form` through the runtime. Each field renderer reads its entry and conditions from the frame. Rendering never writes anything. |
| **Update** (Livewire) | `updating()` refuses paths that are not declared fields. Repeater operations are server methods (`addItem`, `removeItem`, `moveItem`). |
| **Submit** | `FormHost::submit()` executes the gcego/actions action. The engine re-resolves the record (scoped), re-authorizes, opens the transaction, and asks `FormInputResolver` for input. That runs normalize, validate (`ValidationException` becomes a `ValidationFailed` result) and dehydrate. The action handler then persists. |

## Service lifetimes

| Lifetime | Services |
| --- | --- |
| Singletons (immutable or stateless) | `FormsConfig`, `FormManager` (schema cache), `ConditionEvaluator`, `StateHydrator`, `FormInputResolver` |
| Scoped (per request or job) | `FormRenderContext`, `OptionsResolver`, `ValidationEngine`, `StateDehydrator`, `FormInspector` |

User-dependent services (security, data) are resolved per call.
