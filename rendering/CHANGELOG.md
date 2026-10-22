# Changelog

All notable changes to `gcego/rendering` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-22

### Added

- The rendering layer of gcego/runtime 0.1 as its own package, namespace `Gcego\Rendering`: renderers (`Renderers\RendererRegistry`, `Renderers\RendererBinding`, `Renderers\ComponentRenderer`, `Renderers\ContainerRenderer`), `Context\RenderContext`, `Context\RenderMode`, output (`Output\RenderResult`, `Output\Children`), `Blade\BladeRenderer`, styling (`StylingResolver`, `StyleSlots`, `NullStylingResolver`), state (`StatefulComponent`, `StatePath`, `StateCoordinator`), `RenderAuthorizer` with the fail-closed `GateRenderAuthorizer`, the `gcego-rendering` views and `<x-gcego-rendering::render>`.
- `RenderEngine`: `render()`, `renderAuthorized()`, `authorizedBlueprint()`, `identityOf()`, `canRender()` and `operation()`.
- `Rendering` facade (`Gcego\Rendering\Facades\Rendering`, auto-aliased) for `RenderEngine`.
- The `render` operation category in gcego/runtime's handler registry (`Contracts\RenderHandler`, `Execution\RenderOperationHandler`).
- `RenderingConfig` (`gcego-rendering.strict`, on by default outside production and overridable with `GCEGO_RENDERING_STRICT`, `.validate`, `.max_depth`, `.authorization.ability`), `RenderingInspector` and `RenderingReport`.
- `RenderingServiceProvider`, the `gcego/rendering` GCEGO package (requires core, registry, blueprints, runtime; capability `rendering`).

See the migration table in the README for renamed classes, views, settings and abilities.
