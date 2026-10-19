# API reference

All classes live under `Gcego\Blueprints`.

## `Blueprint` (extends `Gcego\Components\Component`, implements `BlueprintInterface`)

| Member | Description |
| --- | --- |
| `const Name`, `Title`, `Description`, `Icon`, `Slug` | property keys (`Name` and `Description` are inherited) |
| `__construct(mixed ...$items)` | optional leading name, then `Property`, `BlueprintPart` and `ConfigurationNode` items |
| `static compositionPolicy(): CompositionPolicy` | final; cached per class |
| `protected static definePolicy(): CompositionPolicy` | override to tighten rules |
| `metadata(): BlueprintMetadata`, `title(): ?string`, `slug(): ?string` | |
| `identity(): BlueprintIdentity` | throws `InvalidBlueprintException` without a valid name |
| `composition(): BlueprintComposition` | cached per instance |
| `part(string $role): ?BlueprintPart` | see [lookup](composition.md#lookup) |
| `requirePart(string $role): BlueprintPart` | |
| `parts(?string $role = null): BlueprintPartCollection` | |
| `hasPart(string $role): bool`, `roles(): list<string>` | |
| `withPart(BlueprintPart $part): static`, `withoutRole(string ...$roles): static` | immutable |

All `Component` methods (`get()`, `validate()`, `assertValid()`,
`normalize()`, `tree()`, `withProperty()`, `withChildren()`, ...) are
available unchanged.

## `BlueprintRole` (final readonly)

`__construct(string $name, bool $multiple = false)`, `static single(string)`,
`static multiple(string)`, `static isValidName(mixed)`, `toArray()`. Public
`name` and `multiple`. Constants `Source`, `Table`, `Form`, `Infolist`,
`Action`, `Widget`, `Layout`, `Navigation` and `NamePattern`.

## `BlueprintCollection` (final readonly, implements `BlueprintResolver`, `Countable`, `IteratorAggregate`)

`__construct(BlueprintInterface ...)`, `has()`, `get(): ?BlueprintInterface`,
`resolve(): BlueprintInterface` (throws `UnknownBlueprintException`), `names()`,
`all()`, `with(...)`, `without(...$names)`, `filter(Closure)`, `toArray()`,
`isEmpty()`, `count()`. Iterates `name => blueprint`.

## Contracts

| Interface | Members |
| --- | --- |
| `BlueprintPart` (extends `ComponentInterface`) | `blueprintRole(): BlueprintRole` |
| `PartContainer` (extends `BlueprintPart`) | marker: its `BlueprintPart` children are parts too |
| `BlueprintInterface` (extends `ComponentInterface`) | `compositionPolicy()`, `name()`, `metadata()`, `identity()`, `composition()`, `part()`, `requirePart()`, `parts()`, `hasPart()`, `roles()` |
| `BlueprintResolver` | `has(string)`, `resolve(string)`, `names()` |

## Composition

| Class | Members |
| --- | --- |
| `BlueprintComposer` | `compose(BlueprintInterface): BlueprintComposition` |
| `BlueprintComposition` | `parts()`, `ofRole()`, `has()`, `roles()`, `role(): ?BlueprintRole` (effective cardinality), `issues()`, `hasIssues()`, `messages()` |
| `BlueprintPartCollection` | `all()`, `entries()`, `first()`, `get()`, `paths()`, `ofRole()`, `isEmpty()`, `count()`, iteration over parts |
| `ClassifiedPart` | public `part`, `role`, `path`; `toArray()` |
| `CompositionPolicy` | `__construct(list<string> $requiredRoles = [], ?list<string> $allowedRoles = null, list<string> $singleRoles = [])`, `withRequiredRoles()`, `withAllowedRoles()`, `withSingleRoles()`, `isRequired()`, `isAllowed()`, `forcesSingle()`, `toArray()` |

## Metadata and identity

| Class | Members |
| --- | --- |
| `Metadata\BlueprintMetadata` | public `name`, `title`, `slug`, `description`, `icon`, `titleDerived`, `slugDerived`; `static of(ComponentInterface)`, `toArray()` |
| `Support\BlueprintIdentity` | public `name`, `slug`, `class`, `type`; `key()`, `equals()`, `toArray()` |
| `Support\Naming` | `isName()`, `isSlug()`, `isIcon()`, `isTitle()`, `slugify()`, `humanize()`; pattern and length constants |

## Validation

| Class | Members |
| --- | --- |
| `BlueprintValidator` | `validate(BlueprintInterface): BlueprintValidationResult`, `assertValid(BlueprintInterface): void` |
| `BlueprintValidationResult` | public `blueprint`; `isValid()`, `errors()`, `first()`, `errorsWithCode(BlueprintErrorCode\|string)`, `errorsForRole()`, `throwIfInvalid()`, `format()`, `toArray()`, `count()`, iteration |
| `BlueprintValidationError` | public `code`, `message`, `blueprint`, `path`, `role`, `expected`, `received`, `hint`, `componentError`; `static fromComponentError()`, `isBlueprintError()`, `format()`, `toArray()` |
| `BlueprintErrorCode` | `InvalidRole`, `DuplicateSingleton`, `RoleConflict`, `RoleNotAllowed`, `MissingRequiredRole` |

## Normalization and inspection

| Class | Members |
| --- | --- |
| `Normalization\BlueprintNormalizer(bool $includeSchema = false)` | `normalize(BlueprintInterface): array` |
| `Inspection\BlueprintInspector` | `inspect(BlueprintInterface): BlueprintInspection` |
| `Inspection\BlueprintInspection` | public `name`, `title`, `slug`, `class`, `type`, `componentCount`, `parts`, `missingRequiredRoles`, `missingOptionalRoles`, `conflicts`, `errors`, `normalized`; `isValid()`, `toArray()` |

## Exceptions (all implement `Exceptions\BlueprintException`)

| Exception | Base | Thrown by |
| --- | --- | --- |
| `InvalidBlueprintException` | `DomainException` | strict validation; `identity()` / `BlueprintCollection` without a valid name |
| `InvalidBlueprintPartException` | `InvalidArgumentException` | invalid role identifiers |
| `DuplicateBlueprintPartException` | `LogicException` | `part()` when several parts claim a single role |
| `MissingBlueprintPartException` | `OutOfBoundsException` | `requirePart()` |
| `BlueprintCompositionException` | `LogicException` | `part()` on a multiple role; invalid policies |
| `DuplicateBlueprintException` | `InvalidArgumentException` | `BlueprintCollection` duplicate names |
| `UnknownBlueprintException` | `OutOfBoundsException` | `BlueprintCollection::resolve()` |
