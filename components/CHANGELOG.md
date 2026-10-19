# Changelog

All notable changes to `gcego/components` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- A string-backed enum case given to a property that takes strings, but not that enum, is stored as its value (`new Property(Resource::Icon, Heroicon::Home)` holds `"heroicon:home"`). Properties typed for the enum keep the case, and unit enums are unchanged.
- `Contracts\PlacementConfiguration`: configuration nodes that tell a component's parent how to place it (a grid column span). Every component accepts them, whatever its allowed configuration nodes.
- `Component` base class with variadic declarative constructor, class-constant property keys and immutable `with*()` copies that preserve the concrete subtype.
- `Property` immutable value object with key validation and safe value checks.
- `PropertyDefinition`, `Type` and `ComponentDefinition` schemas with required properties, defaults, unions, enums, class strings, allowed values, custom rules, child rules and constraints.
- Shorthand constructors via `Component::shorthand()`.
- `ConfigurationNode` contract for non-renderable configuration objects.
- `ValueObject` and `RuntimeValue` contracts for object property values.
- `PropertyCollection`, `ComponentCollection` and `ConfigurationCollection`.
- `ComponentTree`, `ComponentPath`, `TreeNode` and `ComponentIdentity` for occurrence-aware trees.
- `ComponentWalker` with iterative pre-order, post-order and breadth-first traversal, cycle detection and depth limits.
- `ComponentValidator`, `PropertyValidator`, `ChildValidator`, `ValidationResult` and `ValidationError` with collected and strict modes.
- `ComponentNormalizer` with deterministic, transport-independent output.
- `ComponentInspector` for developer tooling.
- Exception hierarchy under `Gcego\Components\Exceptions`.
