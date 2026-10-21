# Architecture

`gcego/morfaw` is the lowest layer of GCEGO Admin Studio. It describes
*what* an interface consists of. It never renders, stores or authorizes anything.

## Design principles

1. **Declarative objects, not arrays.** Components are declared with
   `new Component(...)` and typed constructor items.
2. **Complexity lives in the framework.** Schema, defaults, shorthand and
   validation are declared once per component class. Application code declares
   values only.
3. **Immutable definitions.** Every value object is `readonly`, collections are
   immutable, and components change only through copy methods.
4. **Explicit contracts.** Items are classified by interface, not by namespace
   or naming conventions. Constructors are never invoked during validation.
5. **Provider-owned initialization.** `ComponentServiceProvider` registers every
   runtime service and factory. The `Components` facade resolves the registered manager for
   package operations; Illuminate is a required runtime dependency.

## Class map

```text
Gcego\Components
├── Component                  base declaration type
├── Facades\                   Components facade
├── ComponentServiceProvider   required discovery and service registration
├── ComponentManager           facade-backed orchestration and factories
├── Properties\                immutable Property value
├── Foundation\                NamedComponent, SchemaComponent (catalog base), Makeable make() trait
├── Concerns\                  CanBeHidden, CanSpanColumns, HasColumns, HasExtraAttributes property sets
├── Evaluation\                Computed values, EvaluationContext, Evaluator, State, Get, Set
├── Configuration\             ComponentConfigurator: configureUsing() modifiers, macros, extensions
├── Live\                      LiveSchema, ResolvedComponent, Changes: state, reactivity and diffs
├── Patching\                  SchemaPatch: selector-based immutable schema edits
├── Layout\                    Schema, Section, Group, Item and layout families
├── Forms\                     form controls and shared field contracts
├── Catalog\                   scoped alias-to-component registry
├── Actions\                   action declarations
├── DataDisplay\               data-display declarations
├── Navigation\                navigation declarations
├── Feedback\                  feedback declarations
├── Media\                     media declarations and shared media contracts
├── Schema\                    ComponentDefinition, PropertyDefinition, Field, Type, TypeKind
├── Collections\               PropertyCollection, ComponentCollection, ConfigurationCollection
├── Trees\                     ComponentTree, ComponentPath, TreeNode
├── Contracts\                 component, configuration, builder and value contracts
├── Building\                  ComponentBuilder
├── Validation\                validators, results, errors and error codes
├── Normalization\             ComponentNormalizer, RuntimePlaceholder
├── Inspection\                ComponentInspector, ComponentInspection
├── Traversal\                 ComponentWalker, TraversalOrder, Visit, AncestorChain
├── Support\                   argument parsing, identity, diagnostics and snapshots
└── Exceptions\                ComponentException and package exceptions
```

## Responsibility ownership

| Responsibility | Owner | Related objects with distinct roles |
| --- | --- | --- |
| Registration and service lifetimes | `ComponentServiceProvider` | The facade exposes the manager; neither registers competing services. |
| Factories and scoped schema cache | `ComponentManager` | `ComponentCatalog` resolves trusted aliases to classes. |
| Immutable definition composition | `Building\ComponentBuilder` | One recursive classifier handles mixed items and child-only lists. |
| Strict positional constructor parsing | `Support\ConstructorArguments` | Constructors reject named arguments and do not flatten arbitrary item lists, unlike builders. |
| Property value safety | `Properties\Property` | `ValueSnapshot` detaches array references after validation. |
| Property schema | `Schema\PropertyDefinition` | `Schema\Field` builds that value object; `Type` describes accepted types. |
| Runtime form control | `Forms\FieldComponent` | A named component with form settings, distinct from a property-schema builder. |
| Traversal and occurrence paths | `Traversal\ComponentWalker` | `Trees\ComponentTree` adds a lazy index and queries, not a second traversal engine. |
| Schema validation | `Validation\ComponentValidator` | Delegates property checks and child/configuration checks to their respective validators. |
| Exported values | `Normalization\ComponentNormalizer` | `ComponentInspector` reuses normalization and validation for diagnostic snapshots. |

Collections intentionally retain separate contracts: properties are keyed and
reject duplicate construction keys; children are ordered component occurrences;
configuration collections select non-renderable nodes by class. A generic
collection would erase these distinctions.

Shared catalog schemas live in family bases: layout blocks, titled blocks and
overlays; text, numeric, choice and temporal fields; messages; navigation items;
actions; textual displays; and media. Concrete classes declare distinct renderer
type identifiers and only their additional properties. A distinct type is not a
second implementation of its family logic. Foundation code does not render,
perform HTTP requests, authorize actions or persist state.

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

## Initialization

Laravel discovers `ComponentServiceProvider` through Composer metadata. Its
`ComponentManager` binding is required before component construction, schema
access or runtime operations. No component method constructs independent runtime
services. Validation, normalization, inspection and trees resolve the provider's
services through the `Components` facade, and their dependency constructors have
no service defaults. The facade application is initialized by Laravel; tests clear
resolved facade instances when switching application containers.

## Lifecycle

```text
new X(...)          structural checks: item kinds, key syntax, value safety, duplicates
X::definition()     schema built per class and cached within the active Laravel scope
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
using a definition. `Component::validate()` memoizes its result per instance and
active manager scope. A weak reference prevents the validation cache from keeping
a previous manager and its request context alive.

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
  and resets the memoized validation result and its weak scope reference.
- Shallow cloning is safe because everything a component holds is immutable:
  - validated arrays are recursively copied to detach PHP references;
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
`stableKey()` returns `id:<explicit-id>` when an id is present, otherwise
`path:<occurrence-path>`. These namespaces prevent ids from colliding with paths.
No random ids are generated.

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

- Definitions are built once per class within the active Laravel scope.
- Property lookup is a single array access.
- Traversal is lazy and iterative. Paths and ancestor chains share their parents,
  so memory per node is constant.
- Laravel discovers the provider once; the manager and schema cache are scoped
  to the current request or job. Stateless runtime services are shared, while
  builders and root-specific trees are created afresh.

`composer benchmark` builds a 201-component page and times construction,
lookup, traversal, validation and normalization.

## Layout API

`Components::schema()` resolves the required provider-backed manager and returns
an immutable `Layout\Schema` component. Static section/group/item factories
produce builders for concrete classes under `Layout`; they share the existing
component contracts, validators, normalizer, inspector and traversal services.
They introduce no separate registry or process-wide cache. `Component::collect()`
returns the existing `Collections\ComponentCollection` after building its entries.

Nesting methods accept child components and builders, nested iterables and null
entries. Properties and configuration nodes belong in `set()`/`with()` and
`configure()`, respectively; child lists reject them. As with other components,
schema rules run through `validate()` or `assertValid()`, not during construction.
Sections and groups accept custom component classes. Their visual roles do not
impose an additional class hierarchy on consuming packages.

## Catalog lifecycle

`Catalog\ComponentCatalog` is provider-advertised and scoped. The manager
resolves trusted aliases and uses the existing builder factory. Custom aliases
reset with request/job scopes; register them via a provider `afterResolving`
callback. Built-in classes live in `Layout`, `Forms`, `DataDisplay`, `Navigation`,
`Feedback`, `Actions` and `Media`. Shared form bases centralize repeated field rules.

## Base-class migration

The shared named-component base is `Foundation\NamedComponent` (formerly
`Layout\NamedComponent`), since form, media, navigation and feedback components
also use it. The abstract form-control base is `Forms\FieldComponent` (formerly
`Forms\Field`). `Schema\Field` remains the fluent property-definition builder.
Update custom base-class imports accordingly. There are no duplicate compatibility
wrappers or aliases. Built-in factory aliases, component class names, type strings,
property contracts and normalized output remain unchanged.

`composer check` includes strict optimized Composer autoload checks for PSR-4
violations and ambiguous class declarations. Only namespaced fixtures are included
in development autoloading; Pest loads test files and their local helpers itself.

## Documentation examples

`examples/full-usage.php` is an application declaration returning a validated
schema; `examples/run.php` supplies standalone Laravel initialization. Custom
example classes are kept in their respective folders outside production
autoloading. Integration tests execute the examples against the required provider,
so the documented composition and normalized contracts are checked with the package.
