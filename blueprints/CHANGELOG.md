# Changelog

All notable changes to `gcego/blueprints` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `Blueprint`, a `gcego/morfaw` Component with name shorthand, metadata properties (`Name`, `Title`, `Description`, `Icon`, `Slug`), derived title and slug, and `BlueprintPart`-only children.
- `BlueprintRole` and the `BlueprintPart` / `PartContainer` contracts for open-ended, cardinality-aware role classification.
- `BlueprintComposer`, `BlueprintComposition`, `BlueprintPartCollection`, `ClassifiedPart` and `CompositionPolicy`.
- Lookup: `part()`, `requirePart()`, `parts()`, `hasPart()`, `roles()`; immutable `withPart()` and `withoutRole()`.
- `BlueprintMetadata`, `BlueprintIdentity` and `Naming`.
- `BlueprintValidator`, `BlueprintValidationResult`, `BlueprintValidationError` and `BlueprintErrorCode`.
- `BlueprintNormalizer` and `BlueprintInspector` / `BlueprintInspection`.
- `BlueprintCollection` implementing the `BlueprintResolver` contract.
- Exception hierarchy under `Gcego\Blueprints\Exceptions`.
