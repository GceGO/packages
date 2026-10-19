# API reference

All classes live under `Gcego\Runtime`.

## `Runtime` (final readonly)

| Method | Notes |
| --- | --- |
| `render(ComponentInterface\|string $target, RenderMode $mode = Static, ?string $identity = null, ?string $panel = null, ?string $theme = null, int $headingLevel = 1, array $metadata = [], array $state = []): RenderResult` | no authorization |
| `renderAuthorized(string $identifier, RenderMode $mode = Static, ?string $panel = null, ?string $theme = null, int $headingLevel = 1, array $metadata = [], array $state = []): RenderResult` | `RenderAuthorizer` first |
| `hostedBlueprint(string $identifier): BlueprintInterface` | blueprint kind only; authorized; validated |
| `identityOf(string $identifier): string` | canonical identity |
| `canRender(ComponentInterface): bool` | |

## Contracts

| Interface | Methods |
| --- | --- |
| `Contracts\RendererInterface` | `render(ComponentInterface, RenderContext): RenderResult` |
| `Contracts\StylingResolver` | `classes(ComponentInterface, string $slot, RenderContext): string` |
| `Contracts\StatefulComponent` (extends `ComponentInterface`) | `initialState(): array`, `acceptState(string $field, mixed $value): mixed` |
| `Contracts\RenderAuthorizer` | `authorize(DefinitionDescriptor): void` (throws `AuthorizationException`) |

## Rendering

| Class | Members |
| --- | --- |
| `Rendering\RendererRegistry` | `register(string $target, string\|RendererInterface $renderer, bool $inherit = true, bool $override = false)`, `has()`, `for(ComponentInterface, string $path = '')`, `bindingFor(string $class)`, `bindings()`, `overrides()` |
| `Rendering\RendererBinding` | public `target`, `renderer`, `inherit`; `rendererClass()`, `sameAs()`, `toArray()` |
| `Rendering\ComponentRenderer` | `render(ComponentInterface, RenderContext): RenderResult`, `children(RenderContext, ?int $headingLevel = null): Children`, `registry()` |
| `Rendering\RenderContext` | `static root(...)`, `child()`, `component()`, `path()`, `depth()`, `isLivewire()`, `nextHeadingLevel()`, `hasAncestor()`, `domId()`, `statePath()`, `state()`, `wireModel()`; public `node`, `identity`, `mode`, `locale`, `panel`, `theme`, `headingLevel`, `metadata`, `state` |
| `Rendering\RenderResult` | `static view()`, `static text()`, `static trustedHtml()`, `static empty()`, `toHtml()` (`deferred()` is internal) |
| `Rendering\Children` | `nodes()`, `render(int $index)`, `isEmpty()`, `count()`, `toHtml()`, iteration over `RenderResult`s |
| `Rendering\RenderMode` | `Static`, `Livewire` |
| `Rendering\BlueprintRenderer`, `Rendering\ContainerRenderer` | built-in renderers |
| `Blade\BladeRenderer` (abstract) | `view(): string`, `data()`, `childHeadingLevel()`, `render()` |

## State, styling, security

| Class | Members |
| --- | --- |
| `State\StateCoordinator` | `initialState(ComponentInterface)`, `sanitize(ComponentInterface, array)`, `accept(ComponentInterface, string $property, mixed $value)`, `occurrences(ComponentInterface)` |
| `State\StatePath` | `static for(ComponentPath)`, public `key`, `property(string $field)`; constants `Property`, `FieldPattern` |
| `Styling\StyleSlots` | `__invoke(string $slot): string`, `attribute(string $slot): HtmlString` |
| `Styling\NullStylingResolver` | default `StylingResolver` |
| `Security\GateRenderAuthorizer` | default `RenderAuthorizer` |

## Livewire

`Livewire\BlueprintHost` (final): public `blueprint` (`#[Locked]`) and `state`,
plus `mount()`, `hydrate()`, `updating()`, `updated()` and `render()`.

## Diagnostics

| Class | Members |
| --- | --- |
| `Diagnostics\RuntimeInspector` | `inspect(ComponentInterface\|string\|null $target = null): RuntimeReport` (never throws) |
| `Diagnostics\RuntimeReport` | public `diagnostics`, `renderers`, `missingRenderers`; `check(string)`, `isHealthy()`, `toArray()` |
| `Diagnostics\RenderDiagnostic` | public `check`, `level`, `message`, `details`; `toArray()` |

Checks: `config`, `renderers.override`, `views`, `livewire`, `target`,
`target.renderers` and `target.validation`.

## Configuration (`RuntimeConfig`)

| Key | Default |
| --- | --- |
| `gcego-runtime.rendering.strict` | `true` |
| `gcego-runtime.rendering.validate` | `true` |
| `gcego-runtime.rendering.max_depth` | `64` |
| `gcego-runtime.livewire.enabled` | `true` |
| `gcego-runtime.livewire.alias` | `gcego-blueprint` |
| `gcego-runtime.authorization.ability` | `gcego-runtime.render` |

## Exceptions (all implement `Exceptions\RuntimeException`)

`RendererNotFoundException`, `RendererConflictException`,
`InvalidRenderContextException`, `InvalidStateException` and
`RenderException`. Authorization failures are
`Illuminate\Auth\Access\AuthorizationException`.

## Service provider

`RuntimeServiceProvider` (implements `Gcego\Core\Contracts\PackageInterface`):

- views namespace `gcego-runtime`;
- publish tags `gcego-runtime-config` and `gcego-runtime-views`;
- singletons `Runtime`, `RendererRegistry`, `ComponentRenderer`,
  `StateCoordinator`, `RuntimeConfig`, `StylingResolver` and `RenderAuthorizer`;
- the Livewire alias.
