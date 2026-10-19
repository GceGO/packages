# Blade

## Namespace and views

Views are registered under `gcego-runtime`:

| View | Used by |
| --- | --- |
| `gcego-runtime::renderers.blueprint` | `BlueprintRenderer` |
| `gcego-runtime::renderers.container` | `ContainerRenderer` (generic `Component`) |
| `gcego-runtime::errors.component` | the non-strict error boundary |
| `gcego-runtime::hosts.blueprint` | the Livewire host's root element |
| `gcego-runtime::components.render` | `<x-gcego-runtime::render>` |

### Overriding

Laravel's convention applies: put a file at
`resources/views/vendor/gcego-runtime/renderers/blueprint.blade.php`, or
publish all views with `php artisan vendor:publish --tag=gcego-runtime-views`.
Views are compiled and cached by Laravel as usual (`php artisan view:cache`).

## Template variables (any `BladeRenderer`)

| Variable | Type | Use |
| --- | --- | --- |
| `$component` | the component | `{{ $component->get('label') }}`; **always** with `{{ }}` |
| `$context` | `RenderContext` | `{{ $context->domId('title') }}`, `$context->headingLevel`, ... |
| `$children` | `Children` | `{{ $children }}`, or `@foreach ($children as $child) {{ $child }} @endforeach` |
| `$styles` | `StyleSlots` | `{{ $styles('root') }}` or `<div{{ $styles->attribute('root') }}>` |
| plus `data()` | | renderer-specific values |

`$children` renders lazily, in order, exactly where you print it. Each child is
rendered by its own renderer with a derived context. Configuration nodes are
never children.

## Escaping policy

- **Dynamic text:** print it with `{{ }}`. Blade escapes it.
- **Never** use `{!! !!}` with component properties or state.
- `RenderResult`, `Children` and `$styles->attribute()` are `Htmlable`, so
  `{{ }}` prints them as markup without double escaping. Their contents are
  produced by escaped templates.
- Raw HTML is possible only through `RenderResult::trustedHtml()`, in renderer
  PHP code, for markup you wrote.

## Static rendering from a template

```blade
<x-gcego-runtime::render target="blueprint:subjects" />
<x-gcego-runtime::render target="subjects" panel="admin" />
```

This resolves the definition through the registry, checks the `RenderAuthorizer`
(see [security](security.md)) and prints the result. `Runtime::render()` in PHP
is the unauthorized, trusted-code variant.
