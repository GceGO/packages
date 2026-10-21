# GCEGO packages

Start with [components](components/README.md), the shared component foundation.
It owns property and schema definitions, immutable composition, validation,
normalization, traversal, inspection and the component catalog. Other packages
extend these contracts with their own behavior and rendering.

Register `Gcego\Components\ComponentServiceProvider` before constructing
components. Laravel discovers it through Composer; runtime also registers it
for applications that explicitly load package providers. Standalone tools and
package tests must register it in their application container.

Use the foundation's registered services rather than constructing separate
validators, inspectors, normalizers or trees. `Components::validate()`,
`Components::normalize()`, `Components::inspect()` and `Components::tree()`
use the current application's configuration. Resolve `ComponentWalker` through
the container for traversal; an explicit depth limit may be used for diagnostics.

The current namespaces under `Gcego\Components` are:

| Responsibility | Namespace |
| --- | --- |
| Property values | `Properties` |
| Component, property and configuration collections | `Collections` |
| Component definitions, property definitions and types | `Schema` |
| Trees, paths and nodes | `Trees` |

Update application imports, generators, stubs and examples together. The
[migration guide](components/docs/laravel.md#namespace-migration) lists the moved
classes. Package-specific components retain their domain contracts and add
behavior on top of this foundation.

Run `composer test` from each package directory. Components also provides
`composer check` for its full release checks. Chroma owns the shared stylesheet
build and size budget; consumer packages check their own class coverage.
