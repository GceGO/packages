# API reference

All classes live under `Gcego\Components`.

## `Component` (class, implements `ComponentInterface`)

| Member | Description |
| --- | --- |
| `const Name`, `Label`, `Description`, `Id` | built-in property keys (`'name'`, `'label'`, `'description'`, `'id'`) |
| `const IdPattern` | `/^[A-Za-z][A-Za-z0-9_.:\-]*$/` |
| `__construct(mixed ...$items)` | properties, children, configuration nodes, and leading shorthand values |
| `static definition(): ComponentDefinition` | cached schema (final) |
| `protected static define(): ComponentDefinition` | override to declare the schema |
| `protected static shorthand(): list<string>` | override to enable positional values |
| `type(): string` | schema type identifier |
| `id(): ?string` / `name(): ?string` | explicit id / name |
| `properties(): PropertyCollection` | explicit properties |
| `get(string $key, mixed $fallback = null): mixed` | explicit value, then default, then fallback |
| `require(string $key): mixed` | throws `MissingPropertyException` |
| `has(string $key): bool` / `hasExplicit(string $key): bool` | |
| `defaults(): array<string, mixed>` / `effectiveProperties(): array<string, mixed>` | |
| `children(): ComponentCollection` / `childCount(): int` / `hasChildren(): bool` | |
| `configurations(): ConfigurationCollection` | |
| `validate(): ValidationResult` / `assertValid(): static` | |
| `normalize(): array` / `tree(): ComponentTree` | |
| `withProperty(Property ...)`, `withoutProperty(string ...)`, `withChildren(ComponentInterface ...)`, `withAppendedChildren(ComponentInterface ...)`, `withConfigurations(ConfigurationNode ...)` | immutable copies returning `static` |

## `Property` (final readonly)

`__construct(string $key, mixed $value = null)`, public `$key` and `$value`,
`const KeyPattern`, `static isSupportedValue(mixed): bool`.

## `PropertyDefinition` (final readonly)

`__construct(string $name, Type|list<Type> $type, bool $required = false, bool $nullable = false, mixed $default = <absent>, ?array $allowedValues = null, ?Closure $rule = null, ?string $ruleDescription = null, ?Closure $normalizer = null, ?string $description = null)`

Public properties: `name`, `types`, `required`, `nullable`, `hasDefault`,
`default`, `allowedValues`, `rule`, `ruleDescription`, `normalizer`,
`description`.

Methods: `acceptsType()`, `allowsValue()`, `satisfiesRule()`, `expectedType()`,
`expectedValues()`, `expectedRule()`, `toArray()`.

## `Type` (final readonly)

`string()`, `int()`, `float()`, `bool()`, `array()`, `list()`, `mixed()`,
`enum(class-string<UnitEnum>)`, `classString(?class-string $parent = null)`,
`instanceOf(class-string)`. Public `kind` (`TypeKind`) and `class`. Methods:
`accepts(mixed): bool`, `describe(): string`.

## `ComponentDefinition` (final readonly)

`__construct(string $type, list<PropertyDefinition> $properties = [], bool $allowsUnknownProperties = false, bool $allowsChildren = true, ?list<class-string> $allowedChildren = null, int $minChildren = 0, ?int $maxChildren = null, ?list<class-string> $allowedConfigurations = null, list<Closure> $constraints = [])`

Queries: `hasProperty()`, `property()`, `properties()`, `propertyNames()`,
`requiredProperties()`, `defaults()`, `constraints()`, `allowsChild()`,
`allowsConfiguration()`, `toArray()`.

Modifiers (return a new definition): `withType()`, `withProperties()`,
`withoutProperties()`, `withUnknownProperties(bool = true)`,
`withAllowedChildren(?array $types, int $min = 0, ?int $max = null)`,
`withoutChildren()`, `withAllowedConfigurations(?array)`, `withConstraint(Closure)`.

## Collections (final readonly, `Countable`, `IteratorAggregate`)

| Class | Methods |
| --- | --- |
| `PropertyCollection` | `has`, `get`, `value`, `keys`, `all`, `toArray`, `with`, `without`, `isEmpty`, `count` |
| `ComponentCollection` | `get`, `has`, `first`, `all`, `filter`, `ofType`, `toArray`, `isEmpty`, `count` |
| `ConfigurationCollection` | `has`, `first`, `ofClass`, `all`, `isEmpty`, `count` |

## Trees

| Class | Members |
| --- | --- |
| `ComponentTree` | `root`, `rootNode`, `depthFirst`, `breadthFirst`, `find`, `contains`, `findById`, `findByType`, `parentOf`, `childrenOf`, `descendantCount`, `depth`, `duplicateIds`, `count`, iteration |
| `ComponentPath` | `root`, `fromSegments`, `fromString`, `child`, `parent`, `segments`, `last`, `depth`, `isAncestorOf`, `equals`, `toString` |
| `TreeNode` | `component`, `path`, `depth`, `index`, `parentPath`, `isRoot()`, `identity()` |
| `Support\ComponentIdentity` | `type`, `explicitId`, `path`, `occurrenceKey()`, `stableKey()`, `hasExplicitId()` |

## Traversal

`Traversal\ComponentWalker(int $maxDepth = 256)`: `traverse`, `preOrder`,
`postOrder`, `breadthFirst`, `walk`, `ofType`, `rootNode`, `childNodes`.
`Traversal\TraversalOrder`: `PreOrder`, `PostOrder`, `BreadthFirst`.
`Traversal\Visit`: `Continue`, `SkipChildren`, `Stop`.

## Validation

| Class | Members |
| --- | --- |
| `ComponentValidator` | `validate(ComponentInterface): ValidationResult`, `assertValid(ComponentInterface): void` |
| `PropertyValidator` | `validate(TreeNode, ComponentDefinition): list<ValidationError>`, `validateValue(TreeNode, PropertyDefinition, mixed): ?ValidationError` |
| `ChildValidator` | `validate(TreeNode, ComponentDefinition, list<TreeNode>): list<ValidationError>` |
| `ValidationResult` | `isValid`, `hasErrors`, `errors`, `first`, `errorsAt`, `errorsWithCode`, `merge`, `throwIfInvalid`, `format`, `toArray`, `count`, iteration |
| `ValidationError` | `code`, `message`, `path`, `componentType`, `componentClass`, `subject`, `expected`, `received`, `hint`, `format()`, `toArray()` |
| `ErrorCode` | see [validation](validation.md#error-codes) |

## Normalization and inspection

| Class | Members |
| --- | --- |
| `Normalization\ComponentNormalizer(bool $includeSchema = false)` | `normalize(ComponentInterface): array`, `normalizeValue(mixed, string $location = 'value'): mixed` |
| `Normalization\RuntimePlaceholder` | `class` |
| `Inspection\ComponentInspector` | `inspect(ComponentInterface): ComponentInspection`, `inspectTree(ComponentInterface): list<ComponentInspection>` |
| `Inspection\ComponentInspection` | `class`, `type`, `id`, `path`, `depth`, `supportedProperties`, `requiredProperties`, `allowsUnknownProperties`, `explicitProperties`, `effectiveProperties`, `defaults`, `childCount`, `childrenAllowed`, `allowedChildren`, `minChildren`, `maxChildren`, `configurationClasses`, `errors`, `isValid()`, `toArray()` |

`inspect()` reports errors for the whole subtree. `inspectTree()` reports each
node's own errors.

## Contracts

| Interface | Purpose |
| --- | --- |
| `ComponentInterface` | renderable tree node; extends the four below |
| `PropertyAware` | `properties`, `get`, `has`, `effectiveProperties` |
| `HasChildren` | `children`, `childCount`, `hasChildren` |
| `Validatable` | `validate`, `assertValid` |
| `Normalizable` | `normalize(): mixed` |
| `ConfigurationNode` | non-renderable constructor item (extends `Normalizable`) |
| `ValueObject` | immutable object property value (extends `Normalizable`) |
| `RuntimeValue` | opaque runtime-only property value (marker) |
