# API reference

All classes live under `Gcego\Components`.

## `Component` (class, implements `ComponentInterface`)

| Member | Description |
| --- | --- |
| `const Name`, `Label`, `Description`, `Id` | built-in property keys (`'name'`, `'label'`, `'description'`, `'id'`) |
| `const IdPattern` | `/\A[A-Za-z][A-Za-z0-9_.:\-]*\z/` |
| `__construct(mixed ...$items)` | properties, children, configuration nodes, and leading shorthand values |
| `static define(mixed ...$items): ComponentBuilder` | start an immutable fluent builder |
| `static section(string $name)`, `group(string $name)`, `item(string $name): ComponentBuilder` | named layout builders; items disallow children |
| `static collect(iterable $components): ComponentCollection` | builds dynamic component lists, skipping null entries |
| `static builderClass(): class-string<ComponentBuilder>` | override to choose a custom builder |
| `static definition(): ComponentDefinition` | cached schema (final) |
| `protected static defineSchema(): ComponentDefinition` | override to declare the schema |
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
| `static configureUsing(Closure $modifier, ?Closure $during = null, bool $isImportant = false): mixed` | modifies every `make()` builder of the class and its subclasses |
| `static macro(string $name, Closure $macro): void` / `static hasMacro(string $name): bool` | builder macros bound to the builder; they must return it |
| `protected static setUp(ComponentBuilder $builder): ComponentBuilder` | per-class `make()` defaults, applied before `configureUsing()` |
| `isComputed(string $key): bool` | whether the property holds a closure |
| `evaluateProperty(string $key, EvaluationContext\|array $context = []): mixed` | calls a computed value with injection, checks the result, and falls back to the default on null |
| `evaluatedProperties(EvaluationContext\|array $context = []): array<string, mixed>` | every effective property, evaluated |
| `isHiddenIn(EvaluationContext\|array $context = []): bool` / `isVisibleIn(...)` | `hidden`, `visible`, `hiddenOn`, `visibleOn` for the context's `operation` |
| `displayLabel(EvaluationContext\|array $context = []): ?string` | the evaluated label, or one derived from the name (`first_name` → `First name`) |

## `Facades\Components`

Laravel facade for `ComponentManager`, discovered as the `Components`
alias. Static methods: `define()`, `make()`, `schema()`, `collect()`, `tree()`, `validate()`, `normalize()`
`inspect()` and `normalizeValue()`. `getFacadeRoot(): ComponentManager` returns the registered
manager and fails clearly when Laravel facades or the provider are not initialized.

## `ComponentManager`

Request/job-scoped service behind the `Components` facade, also available through
constructor injection. Managers from inactive applications or scopes reject use.
Debug output hides the container, and native manager serialization is rejected. Methods:
`define(class-string<Component>, array $items = []): ComponentBuilder`,
`make(class-string<Component> $component = Component::class, array $items = []): Component`,
`schema(iterable $components): Layout\Schema`, `collect(iterable $components): ComponentCollection`,
`tree(ComponentInterface): ComponentTree`, `validate(ComponentInterface): ValidationResult`,
`normalize(ComponentInterface): array`, `normalizeValue(mixed, string $location = 'value'): mixed`,
`inspect(ComponentInterface): ComponentInspection`.

## `Building\ComponentBuilder`

`build(): Component` builds children and passes canonical items to the protected
`instantiate(array $items): Component` hook. Override that hook to adapt a custom
component constructor. `buildItems(): list<Property|ComponentInterface|ConfigurationNode>`
exposes the canonical items to custom builders. `prepare(array $items): array`
adapts incoming definition arguments. Built components must match the requested
component class. Nested iterable item lists are limited to 32 levels.

All modifier methods below return the same concrete builder type. Child
transformations receive unbuilt children. No mutable state is shared between
modified copies; custom runtime objects follow their own contract.

| Area | Methods | Behavior |
| --- | --- | --- |
| Properties | `set($key, $value)`, `setMany($properties)`, `setIfMissing($key, $value)` | Merge explicit values; explicit null counts as present. |
| Property selection | `forget(...$keys)`, `onlyProperties(...$keys)`, `clearProperties()`, `replaceProperties($properties)` | Remove, retain or replace explicit properties. |
| Property reads | `get($key, $fallback = null)`, `has($key)`, `properties()` | Read explicit builder state; do not resolve schema defaults or build children. |
| Append children | `schema($children)`, `components($children)`, `child($children)` | Append nodes, builders or nested iterables; skip null entries. |
| Replace/order children | `replaceChildren($children)`, `prependChildren($children)`, `clearChildren()` | Use the same child-contract and nesting-depth checks. |
| Transform children | `filterChildren($predicate)`, `mapChildren($callback)` | Callbacks receive child and zero-based index; map may return null to omit a child. |
| Child reads | `children()`, `childCount()` | Inspect unbuilt children in declaration order. |
| Configuration | `configure(...$nodes)`, `replaceConfigurations(...$nodes)`, `clearConfigurations()` | Append, replace or remove configuration nodes. |
| Composition | `tap($callback)`, `apply($callbacks)` | Callbacks must return the transformed builder; capture their return values. |
| Conditions | `when($condition, $callback, $otherwise = null)`, `unless($condition, $callback, $otherwise = null)` | Only execute the selected branch; return the unchanged builder if no branch is selected. |
| Build | `build()`, `buildValidated()` | Build a component; the validated variant also enforces schema rules. |
| Runtime services | `validate()`, `normalize()`, `tree()`, `inspect()` | Build and invoke the corresponding provider-backed service. |

Each terminal call builds a fresh component; it does not cache arbitrary child
builders. `build()` and `normalize()` do not implicitly validate. Use
`buildValidated()->normalize()` when exporting strictly validated definitions.

## `ComponentServiceProvider`

Required deferred Laravel provider. `register(): void` binds the manager and catalog as scoped,
stateless services as singletons, and builders/trees as transient factories,
preserving existing bindings; `provides(): list<class-string>`
lists those services. See [Laravel integration](laravel.md).

## `Properties\Property` (final readonly)

`__construct(string $key, mixed $value = null)`, public `$key` and `$value`,
`const KeyPattern`, `static isSupportedValue(mixed): bool`.

## `Schema\PropertyDefinition` (final readonly)

`__construct(string $name, Type|list<Type> $type, bool $required = false, bool $nullable = false, mixed $default = <absent>, ?array $allowedValues = null, ?Closure $rule = null, ?string $ruleDescription = null, ?Closure $normalizer = null, ?string $description = null)`

Public properties: `name`, `types`, `required`, `nullable`, `hasDefault`,
`default`, `allowedValues`, `rule`, `ruleDescription`, `normalizer`,
`description`.

Methods: `acceptsType()`, `allowsValue()`, `satisfiesRule()`, `expectedType()`,
`expectedValues()`, `expectedRule()`, `toArray()`.

## `Schema\Type` (final readonly)

`string()`, `int()`, `float()`, `bool()`, `array()`, `list()`, `mixed()`,
`enum(class-string<UnitEnum>)`, `classString(?class-string $parent = null)`,
`instanceOf(class-string)`. Public `kind` (`TypeKind`) and `class`. Methods:
`accepts(mixed): bool`, `describe(): string`.

## `Schema\ComponentDefinition` (final readonly)

`__construct(string $type, list<PropertyDefinition> $properties = [], bool $allowsUnknownProperties = false, bool $allowsChildren = true, ?list<class-string> $allowedChildren = null, int $minChildren = 0, ?int $maxChildren = null, ?list<class-string> $allowedConfigurations = null, list<Closure> $constraints = [])`

Queries: `hasProperty()`, `property()`, `properties()`, `propertyNames()`,
`requiredProperties()`, `defaults()`, `constraints()`, `allowsChild()`,
`allowsConfiguration()`, `toArray()`.

Modifiers (return a new definition): `withType()`, `withProperties()`,
`withoutProperties()`, `withUnknownProperties(bool = true)`,
`withAllowedChildren(?array $types, int $min = 0, ?int $max = null)`,
`withoutChildren()`, `withAllowedConfigurations(?array)`, `withConstraint(Closure)`.

## `Collections` (final readonly, `Countable`, `IteratorAggregate`)

| Class | Methods |
| --- | --- |
| `PropertyCollection` | `has`, `get`, `value`, `keys`, `all`, `toArray`, `with`, `without`, `isEmpty`, `count` |
| `ComponentCollection` | `get`, `has`, `first`, `all`, `filter`, `ofType`, `toArray`, `isEmpty`, `count` |
| `ConfigurationCollection` | `has`, `first`, `ofClass`, `all`, `isEmpty`, `count` |

## Trees

| Class | Members |
| --- | --- |
| `Trees\ComponentTree` | `root`, `rootNode`, `depthFirst`, `breadthFirst`, `find`, `contains`, `findById`, `findByType`, `parentOf`, `childrenOf`, `descendantCount`, `depth`, `duplicateIds`, `count`, iteration |
| `Trees\ComponentPath` | `root`, `fromSegments`, `fromString`, `child`, `parent`, `segments`, `last`, `depth`, `isAncestorOf`, `equals`, `toString` |
| `Trees\TreeNode` | `component`, `path`, `depth`, `index`, `parentPath`, `isRoot()`, `identity()` |
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
| `Normalization\ComponentNormalizer(ComponentWalker $walker, bool $includeSchema = false)` | `normalize(ComponentInterface): array`, `normalizeValue(mixed, string $location = 'value'): mixed`, `normalizeProperties(array $values, ComponentDefinition $definition, string $location = 'component'): array` |
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

## Layout declarations

`Layout\Schema`, `Layout\Section`, `Layout\Group` and `Layout\Item` are
extendable concrete components with type identifiers `schema`, `section`, `group` and `item`.
The facade's `schema()` builds a schema root. Sections, groups and items require
non-empty names. Items disallow children. Call `assertValid()` for strict validation.

Builder methods `components()` and `child()` append children, like `schema()`;
all accept components, builders or iterables containing them and conditional null
entries. `label(mixed $label)` follows the component’s property mapping and validation. Each returns a new builder.

`Foundation\NamedComponent` is the abstract base for sections, groups and items.
It centralizes their required name and shorthand. Extend a concrete layout class
and call `YourLayout::define()` when creating specialized system components.

Tree relationship methods resolve `TreeNode` arguments by path and component
identity against their own index. Nodes for unrelated components return null,
an empty child list or zero descendants, as appropriate.

## Component catalog and fluent fields

- `Components::catalog(): Catalog\ComponentCatalog` resolves the scoped registry.
- `Components::component(string $alias, string $name): ComponentBuilder` starts a named component builder.
- `ComponentCatalog::all(): array<string, class-string<Component>>` lists registrations.
- `ComponentCatalog::resolve(string $alias): class-string<Component>` resolves an exact alias.
- `ComponentCatalog::register(string $alias, string $component): void` adds a trusted component class.
- `Schema\Field` fluently builds property definitions; `ComponentDefinition::withFields(iterable<Field|PropertyDefinition>)` merges them.

See the [catalog reference](component-catalog.md) for built-in factories and rules.

## Filament-style authoring

- `Foundation\SchemaComponent` (abstract, extends `NamedComponent`) is the base of every catalog component. It provides `static make(mixed ...$items): ComponentBuilder` (`Foundation\Makeable`, also used by `Layout\Schema`) and the `CanBeHidden`, `CanSpanColumns` and `HasExtraAttributes` concerns.
- `Contracts\Concern::properties(): list<PropertyDefinition>` declares reusable properties. `ComponentDefinition::withConcerns(class-string<Concern> ...)` merges them. The built-in concerns are `Concerns\CanBeHidden` (`hidden`, `visible`, `hiddenOn`, `visibleOn`), `Concerns\CanSpanColumns` (`columnSpan`, `columnStart`), `Concerns\HasColumns` (`columns`) and `Concerns\HasExtraAttributes` (`extraAttributes`). Responsive values take one value or a map keyed by `default`, `sm`, `md`, `lg`, `xl`, `2xl` (`Support\Responsive`).
- Builder additions: closures passed to any setter are stored as `Evaluation\Computed`. Also added: `columnSpanFull()`, `hiddenOn(string|list<string> ...)`, `visibleOn(...)` and `extraAttributes(array|Closure $attributes, bool $merge = false)`. Unknown methods resolve registered macros before property setters.
- `Evaluation\EvaluationContext` (final readonly): `make(array $parameters = [])`, `from(self|array)`, `with(string, mixed)`, `withTyped(object ...)`, `withState(State|array, string $statePath = '')` and `operation(): ?string`.
- `Evaluation\Evaluator`, through `Components::evaluator()`: `evaluate(mixed $value, EvaluationContext $context, ?ComponentInterface $component = null, string $subject = ...): mixed`. Parameters are resolved by name, then by type from the context, then from the container, then from their default or null. Failures throw `Exceptions\EvaluationException`.
- `Evaluation\State` holds mutable dot-path state. `Evaluation\Get` and `Evaluation\Set` are invokable and resolve paths relative to the context's `statePath` (`../` moves up a level; `isAbsolute: true` skips that).
- `Configuration\ComponentConfigurator`, through `Components::configurator()`, is an application singleton holding `configureUsing()` modifiers and macros.

## Live engine and patching

- `Components::live(ComponentInterface|Buildable $schema, State|array $state = [], EvaluationContext|array $context = []): Live\LiveSchema` fills missing defaults and resolves the tree.
- `LiveSchema`: `root(): ResolvedComponent`, `at(string $componentPath)`, `field(string $statePath)`, `isLive(string $statePath): bool`, `livePaths(): list<string>`, `dependentsOf(string $statePath): list<string>`, `set(string $statePath, mixed $value): Changes`, `setMany(array): Changes`, `dehydrate(): array`, `toArray(): array`, `state(): State`, `schema()`.
- `Live\ResolvedComponent` (final readonly): `component`, `path`, `containerPath`, `statePath`, `properties` (evaluated, without `default` and callbacks), `hidden`, `label`, `dependencies` (state paths read), `children`, plus `type()`, `name()`, `get()`, `holdsState()`, `isVisible()`.
- `Live\Changes` (final readonly): `statePaths`, `components` (tree paths in order), `isEmpty()`, `touches(string)`, `toArray()`.
- `Contracts\HoldsState` marks state holders (`Forms\FieldComponent`). `Concerns\HasState` adds `statePath`, `default`, `live`, `dehydrated` and `afterStateUpdated`. `Concerns\HasStatePath` adds `statePath` to containers.
- `PropertyDefinition::isCallback(): bool` is true for properties typed only as `Evaluation\Computed`. `Component::evaluateProperty()` returns their closures uncalled.
- `Evaluation\State::record(Closure): array{result, reads, writes}` and `peek()` (a read that is not recorded).
- `Components::patch(ComponentInterface|Buildable): Patching\SchemaPatch` supports `modify(selector, Closure(ComponentBuilder))`, `remove()`, `insertBefore()`, `insertAfter()`, `append()`, `prepend()` and `apply(): ComponentInterface`. Unmatched selectors throw `Exceptions\PatchException` with a suggestion.
- `Components::extend(string $key, Closure(SchemaPatch): SchemaPatch)` and `Components::extended(string $key, ComponentInterface|Buildable): ComponentInterface`. `Components::walker(): ComponentWalker`.

## Executable reference

See [complete usage](usage.md) for tested declaration and extension files. Built-in
catalog types share family schema contracts; `Foundation\NamedComponent` owns
cross-category naming, and `Forms\FieldComponent` owns form-control configuration.
Examples and their supporting classes are separate from production autoloading.
