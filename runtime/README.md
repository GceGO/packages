# gcego/runtime

Turns GCEGO definitions into HTML with Blade, and hosts registered blueprints
interactively with Livewire. Application developers write no templates,
Livewire classes or dispatch code. Component packages register renderers once.

```blade
{{-- Interactive, from a registered definition --}}
<livewire:gcego-blueprint blueprint="blueprint:subjects" />

{{-- Static --}}
<x-gcego-runtime::render target="subjects" />
```

```php
app(Gcego\Runtime\Runtime::class)->render($blueprint);              // trusted server code
app(Gcego\Runtime\Runtime::class)->render('blueprint:subjects');    // via gcego/registry
```

## Requirements

- PHP 8.3+, Laravel 13, Livewire 4.4+
- `gcego/components`, `gcego/core`, `gcego/blueprints` and `gcego/registry` ^0.1
- Tailwind CSS v4 and Alpine.js are provided by the application; runtime adds no JavaScript or CSS

## Installation

```bash
composer require gcego/runtime
php artisan vendor:publish --tag=gcego-runtime-config   # optional
php artisan vendor:publish --tag=gcego-runtime-views    # optional, to customize markup
```

The provider is auto-discovered and is the `gcego/runtime` package of
`gcego/core`. No routes are registered.

## Authorization: required before anything renders publicly

Public entry points (the Livewire host and `<x-gcego-runtime::render>`) check
a Gate ability before loading a definition. Laravel denies undefined
abilities, so nothing renders until you decide who may see what:

```php
// App\Providers\AppServiceProvider::boot()
Gate::define('gcego-runtime.render', fn (?User $user, string $identity): bool => $user?->isAdmin() ?? false);
```

A registry identity is never an authorization grant, and rendering a
definition grants no access to its data. See [security](docs/security.md).

## Rendering model

- **`RendererRegistry`** maps component classes and interfaces to renderers.
  It is separate from gcego/registry, which maps names to definitions.
- **A renderer** (`RendererInterface`, usually a `BladeRenderer`) receives the
  component and an immutable `RenderContext` (path, identity, mode, locale,
  heading level, state). It returns a lazy `RenderResult`.
- **Children** are rendered where the parent's template prints `{{ $children }}`.
- **Configuration nodes**, such as styling, are never rendered.
- **Built-in renderers:**
  - every `Blueprint` renders as a labelled `<section>` with its title,
    description and parts in order;
  - the generic `Component` renders as a `<div>`, or as a labelled `<section>`
    when it has a label.

```php
// In a GCEGO package's service provider:
$this->app->make(RendererRegistry::class)->register(Table::class, TableRenderer::class);
```

See the [extension guide](docs/extension-guide.md) for a complete, tested example.

## Styling

Templates ask the `StylingResolver` for classes per slot:
`<h1{{ $styles->attribute('title') }}>`. The default resolver returns nothing,
so the output is plain semantic HTML. `gcego/chroma` will bind a Tailwind
CSS v4 resolver (see [rendering](docs/rendering.md#styling)).

## Documentation

| | |
| --- | --- |
| [Architecture](docs/architecture.md) | Responsibilities, lifecycle, decisions |
| [Rendering](docs/rendering.md) | Runtime API, renderer registry, context, results, errors, styling |
| [Blade](docs/blade.md) | Views, template variables, escaping, overrides |
| [Livewire](docs/livewire.md) | The blueprint host |
| [State](docs/state.md) | Stateful components and state paths |
| [Security](docs/security.md) | Escaping, identities, state, authorization |
| [Extension guide](docs/extension-guide.md) | Writing a component, renderer and view |
| [API reference](docs/api-reference.md) | Every public class and method |

## Development

```bash
composer test       # Pest + Testbench + Livewire testing
composer analyse    # PHPStan (Larastan), level max
composer format     # Pint
composer benchmark  # lookup, nested rendering, repeated rendering, memory
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
