# Architecture

`gcego/components` is the lowest layer of GCEGO Admin Studio. It describes
*what* an interface consists of. It never renders, stores or authorizes anything.

## Design principles

1. **Declarative objects, not arrays.** Components are declared with
   `new Component(...)` and typed constructor items.
2. **Complexity lives in the framework.** Schema, defaults, shorthand and
   validation are declared once per component class. Application code declares
   values only.
3. **Immutable definitions.** Every value object is `readonly`, collections are
   immutable, and components change only through copy methods.
4. **Explicit over magic.** Items are classified by interface, not by namespace
   or naming conventions. No reflection is used at runtime, and constructors are
   never invoked during validation.
5. **Pure PHP.** Zero runtime dependencies.

## Class map

```text
Gcego\Components
├── Component                  base class every component extends
├── Property                   one immutable key/value
├── PropertyDefinition         schema for one property
├── Type / TypeKind            accepted type of a property value
├── ComponentDefinition        schema for one component class
├── PropertyCollection         explicit properties, keyed and ordered
├── ComponentCollection        ordered child components
├── ConfigurationCollection    ordered configuration nodes
├── ComponentTree              lazily indexed tree of occurrences
├── ComponentPath              deterministic occurrence path
├── TreeNode                   one occurrence: component + path + depth
├── Contracts\                 ComponentInterface, PropertyAware, HasChildren,
│                              Validatable, Normalizable, ConfigurationNode,
│                              ValueObject, RuntimeValue
├── Validation\                ComponentValidator, PropertyValidator,
│                              ChildValidator, ValidationResult,
│                              ValidationError, ErrorCode
├── Normalization\             ComponentNormalizer, RuntimePlaceholder
├── Inspection\                ComponentInspector, ComponentInspection
├── Traversal\                 ComponentWalker, TraversalOrder, Visit
├── Support\                   ConstructorArguments, ParsedArguments,
│                              ComponentIdentity, ValueDescriber, Absent
└── Exceptions\                ComponentException (interface) and subclasses
```

### Deviations from the original package plan

- **`Support\ComponentFactory` was not created.** Construction already happens
  through ordinary constructors. A factory would add a second way to build
  components without adding capability. Shorthand parsing lives in
  `Support\ConstructorArguments`.
- **Added `Type`/`TypeKind`.** Union, enum and class-string types need a typed
  descriptor. Parsing type strings such as `"int|string"` would be error-prone.
- **Added `ConfigurationCollection` and the `ConfigurationNode` contract.** These
  keep non-renderable configuration (a future Tailwind modifier, for example) out
  of `children()`.
- **Added `ComponentPath` and `TreeNode`.** Paths are per-occurrence values, so
  components never need parent pointers.
- **Added `ComponentInspection`.** This is the structured result of the inspector.
- `phpunit.xml.dist` and `phpstan.neon.dist` follow the `.dist` convention, so
  developers can override them locally with untracked `phpunit.xml` and
  `phpstan.neon` files.

## Constructor item classification

`ConstructorArguments::parse()` applies these rules to `new X(...$items)`:

| Item | Becomes |
| --- | --- |
| `Property` | an explicit property |
| `ComponentInterface` | a renderable child, in order |
| `ConfigurationNode` | a configuration node, in order |
| any other leading value | a shorthand `Property` for the next key in `X::shorthand()` |

Additional rules:

- A named argument (`new X(name: 'a')`) throws `InvalidConfigurationException`.
- A shorthand value after the first item throws `InvalidConfigurationException`.
- More shorthand values than declared keys throw `InvalidConfigurationException`.
- A duplicate key, including a shorthand value that collides with an explicit
  `Property`, throws `DuplicatePropertyException`.

## Lifecycle

```text
new X(...)          structural checks: item kinds, key syntax, value safety, duplicates
X::definition()     schema built once per class by X::defineSchema(), then cached
$x->validate()      schema checks over the whole tree, all errors collected with paths
$x->assertValid()   the same checks, throwing InvalidComponentException
$x->normalize()     canonical array for registries and runtimes
```

Schema validation is deliberately **not** run in constructors:

- Children are built before their parent exists. A constructor cannot know the
  path of a child, but tree validation can report `subjects.table.name`.
- Developers get every error at once instead of fixing them one exception at a time.
- `with*()` copies would otherwise have to re-validate at every step.

Runtimes and registries must call `assertValid()` (or `validate()`) before
using a definition. `Component::validate()` memoizes its result per instance.
This is safe because instances are immutable.

## Immutability policy

- `Property`, `PropertyDefinition`, `ComponentDefinition`, `Type`, every
  collection, `TreeNode`, `ValidationError`, `ValidationResult` and
  `ComponentIdentity` are `readonly`.
- `ComponentPath` is immutable. Its only internal state is a lazily computed
  string cache.
- `Component` has no public properties. Its internals change only on clones
  inside the `final` copy methods `withProperty()`, `withoutProperty()`,
  `withChildren()`, `withAppendedChildren()` and `withConfigurations()`. These
  return `static`, so copies keep the concrete subclass. `__clone()` is `final`
  and resets the memoized validation result.
- Shallow cloning is safe because everything a component holds is immutable:
  - arrays are copied by value;
  - enum cases are singletons;
  - object property values must implement `ValueObject` (an immutable value) or
    `RuntimeValue` (opaque, never inspected).
- Accessors return arrays (copied on write) or immutable collections, so callers
  cannot mutate a component indirectly.

## Identity

Three identities are kept apart:

| Identity | Source | Scope |
| --- | --- | --- |
| type | `ComponentDefinition::$type`, which defaults to the class name | every instance of a class |
| explicit id | the optional `Component::Id` property | stable across requests |
| occurrence | the deterministic `ComponentPath` | one position in one tree |

`TreeNode::identity()` returns a `ComponentIdentity` holding all three. Its
`stableKey()` prefers the explicit id and falls back to the path. No random ids
are generated.

## Reuse and cycles

One immutable component instance may appear at several positions. Each
occurrence gets its own `TreeNode` and path, for example `root.shared` and
`root.shared#1`. A reused instance that carries an explicit id is reported as
`DuplicateId`, because ids must be unique within a tree.

The base `Component` cannot form cycles, because children must exist before
their parent. Third-party `ComponentInterface` implementations could still
return themselves as children. To guard against that, `ComponentWalker`
tracks ancestors and throws `InvalidChildException`. Traversal is iterative,
and nesting deeper than `ComponentWalker::DefaultMaxDepth` (256) is rejected.

## Performance

- Definitions are built once per class.
- Property lookup is a single array access.
- Traversal is lazy and iterative. Paths and ancestor chains share their parents,
  so memory per node is constant.
- No reflection or framework bootstrapping happens at runtime.

`composer benchmark` builds a 201-component page and times construction,
lookup, traversal, validation and normalization.
