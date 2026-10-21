# Rendering

## The runtime API

```php
use Gcego\Runtime\Runtime;
use Gcego\Runtime\Rendering\RenderMode;

$runtime = app(Runtime::class);

$runtime->render($component);                       // any ComponentInterface
$runtime->render('blueprint:subjects');             // registry identity, or a bare name: 'subjects'
$runtime->render($component, identity: 'blueprint:subjects', headingLevel: 2, panel: 'admin', theme: 'dark', metadata: ['compact' => true]);
$runtime->renderAuthorized('subjects');             // RenderAuthorizer first (public entry points)
$runtime->identityOf('subjects');                   // 'blueprint:subjects'
$runtime->canRender($component);                    // a renderer is registered
```

Every call returns a `RenderResult`. It is lazy: nothing renders until you
print it with `{{ $result }}` or call `->toHtml()`. Definitions are validated
first (`rendering.validate`), and an invalid one raises `RenderException`.
String targets must be registry identifiers; class names and paths are
rejected.

## Renderer registry

```php
use Gcego\Runtime\Rendering\RendererRegistry;

$renderers = app(RendererRegistry::class);

$renderers->register(ExampleTable::class, TableRenderer::class);                 // a class, or
$renderers->register(BlueprintPart::class, PartRenderer::class);                 // an interface
$renderers->register(Component::class, ContainerRenderer::class, inherit: false); // exact class only
$renderers->register(ExampleTable::class, OtherRenderer::class, override: true); // deliberate replacement
```

**Precedence** for a component of class C:

1. a binding for exactly C;
2. the binding of C's nearest parent class, if it has `inherit: true`;
3. the first-registered `inherit: true` binding for an interface C implements.

The built-in `Component` binding has `inherit: false`, so unknown component
types are reported instead of being drawn as empty boxes.

**Conflicts:** registering a different renderer for the same target throws
`RendererConflictException` unless you pass `override: true`. Overrides are
listed by the inspector. Registering the identical binding twice is a no-op.

Renderer classes are built once through the container (constructor injection
works) and reused. They must be stateless. Register renderers from a package
service provider with `$this->app->make(RendererRegistry::class)->register(...)`.

## `RenderContext`

| Member | Meaning |
| --- | --- |
| `node`, `component()`, `path()`, `depth()` | the occurrence (`TreeNode` from gcego/morfaw) |
| `identity` | the definition identity, e.g. `blueprint:subjects` (or `null`) |
| `mode`, `isLivewire()` | `RenderMode::Static` or `RenderMode::Livewire` |
| `locale` | the application locale at render time |
| `panel`, `theme` | optional lowercase identifiers for future panels and styling |
| `headingLevel`, `nextHeadingLevel()` | for a correct `<h1>`–`<h6>` outline |
| `metadata` | approved scalar values |
| `state()`, `statePath()`, `wireModel($field)` | Livewire state of this occurrence (see [state](state.md)) |
| `domId($suffix = '')` | a stable, unique HTML id |
| `child($node, $headingLevel)` | derive a child's context |

The context never contains the request, the user, sessions or connections.

## `RenderResult`

| Factory | Output |
| --- | --- |
| `RenderResult::view($view)` | a Blade view (escaped by Blade) |
| `RenderResult::text($text)` | escaped text |
| `RenderResult::trustedHtml($html)` | raw markup; **only** for HTML your code wrote, never user input |
| `RenderResult::empty()` | nothing |

Results are `Htmlable`, so Blade prints them without double escaping.

## Errors

| Situation | Exception |
| --- | --- |
| no renderer for a component | `RendererNotFoundException` (class, type, path) |
| a renderer or its view throws | `RenderException` naming type, path and renderer; the cause is `getPrevious()` |
| a missing view | `RenderException`: "View [x] used by Renderer does not exist." |
| nesting deeper than `rendering.max_depth`, or a component that contains itself | `RenderException` |
| invalid definition | `RenderException` wrapping `InvalidComponentException` |
| a string that is not an identifier | `RenderException` |

With `rendering.strict` set to `true` (the default), the innermost failure is
thrown. With `false`, it is reported through Laravel's exception handler, and
that component alone is replaced with
`<div role="alert">This section could not be displayed.</div>`. The details
appear only when `app.debug` is true.

## Styling

```blade
<section{{ $styles->attribute('root') }}>
    <h2{{ $styles->attribute('title') }}>{{ $title }}</h2>
```

`StyleSlots` asks the bound `StylingResolver::classes($component, $slot, $context)`
and escapes the result. `NullStylingResolver` (the default) returns `''`, and
`attribute()` then prints nothing.

A Tailwind resolver (gcego/chroma) must return only classes Tailwind's
compiler can find: write them in full in PHP or Blade sources that are listed
as Tailwind sources (`@source`), or safelist them. Never assemble utility
names from runtime input. Built-in slots are `root`, `header`, `title`,
`description` and `body`.
