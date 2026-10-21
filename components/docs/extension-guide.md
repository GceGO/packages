# Extension guide

This guide is for authors of GCEGO packages (`gcego/tables`, `gcego/forms`,
`gcego/chroma`, ...) that build on `gcego/components`.

## 1. Extend `Component`

```php
namespace Gcego\Tables\Columns;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

final class Text extends Component
{
    public const string Searchable = 'searchable';
    public const string Sortable = 'sortable';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('tables.columns.text')
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true),
                new PropertyDefinition(self::Searchable, Type::bool(), default: false),
                new PropertyDefinition(self::Sortable, Type::bool(), default: false),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
```

- `defineSchema()` runs once per class within the active Laravel scope;
  `definition()` caches that scope's schema. Do not retain managers across scopes.
- Start from `parent::defineSchema()` to inherit `Name`, `Label`, `Description` and
  `Id`, or build a fresh `new ComponentDefinition(...)` for full control.
- Choose a namespaced `type`, such as `tables.columns.text`. The type defaults to
  the class name.
- Subclasses reject unknown properties unless they call `withUnknownProperties()`.
- The accessors and copy methods of `Component` are `final`, which protects its
  invariants. Customize through the schema, not by overriding accessors.

## 2. Containers with shorthand

The minimal-code scenario:

```php
return new Table(
    Subject::class,

    new Text('name', 'Subject Name'),
    new Text('code', 'Code'),
);
```

is just:

```php
final class Table extends Component
{
    public const string Model = 'model';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('tables.table')
            ->withProperties(new PropertyDefinition(self::Model, Type::classString()))
            ->withAllowedChildren([Column::class], min: 1);
    }

    protected static function shorthand(): array
    {
        return [self::Model];
    }
}
```

What the model class means (Eloquent or anything else) is decided by the
`tables` and `data` packages. This package only checks that the value names an
existing class, and never instantiates it.

Prefer `shorthand()` to custom constructors: the default builder constructs a
component from canonical `Property`, child and configuration items.

If a constructor requires a scalar argument, give the component a custom builder
that adapts those canonical items through `instantiate()`. Initialization still
runs through the constructor:

```php
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Properties\Property;

final class NamedComponent extends Component
{
    public function __construct(string $name, mixed ...$items)
    {
        parent::__construct(new Property(self::Name, $name), ...$items);
    }

    public static function builderClass(): string
    {
        return NamedComponentBuilder::class;
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}

final class NamedComponentBuilder extends ComponentBuilder
{
    /** @param list<Property|ComponentInterface|ConfigurationNode> $items */
    protected function instantiate(array $items): Component
    {
        $name = $this->propertyValue(Component::Name);

        if (! is_string($name)) {
            throw InvalidBuilderException::invalidValue($this->component, 'name', 'a string name is required');
        }

        $rest = array_values(array_filter(
            $items,
            static fn ($item): bool => ! $item instanceof Property || $item->key !== Component::Name,
        ));

        return new NamedComponent($name, ...$rest);
    }
}

NamedComponent::define('example')->label('Example')->build();
```

The hook receives built children, properties and configuration nodes in declaration
order within each group. `prepare()` adapts incoming builder arguments;
`instantiate()` adapts the final constructor call. The default builder cannot
infer an arbitrary constructor's signature.

## 3. Configuration nodes (styling, behaviour modifiers)

Implement `Contracts\ConfigurationNode` and return plain data from `normalize()`:

```php
final readonly class Tailwind implements ConfigurationNode
{
    public function __construct(public string $classes) {}

    public function normalize(): array
    {
        return ['classes' => $this->classes];
    }
}
```

Configuration nodes are kept apart from renderable children by contract.
Components accept every node unless their schema restricts the list with
`withAllowedConfigurations()`.

## 4. Object property values

- **Immutable data:** implement `Contracts\ValueObject`, preferably as a
  `readonly` class, and declare `Type::instanceOf(YourValue::class)`.
- **Runtime-only behaviour** (callbacks, resolvers): implement
  `Contracts\RuntimeValue`. The engine stores it untouched and normalizes it to a
  `RuntimePlaceholder`. Invoking it is the runtime package's responsibility.

## 5. Consuming trees

Renderers, registries and inspectors should:

1. call `$root->assertValid()` once, when a definition is registered;
2. use `ComponentWalker`, `ComponentTree` or `$root->normalize()` instead of
   recursing manually;
3. key per-occurrence state by `TreeNode::identity()->stableKey()` (`id:…` or `path:…`).

## 6. Testing custom components

```php
it('supports shorthand and advanced syntax equally', function (): void {
    expect((new Text('name', 'Name'))->normalize())
        ->toBe((new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Name')))->normalize());
});

it('rejects children', function (): void {
    $result = (new Text('name'))->withChildren(new Text('other'))->validate();

    expect($result->first()?->code)->toBe(ErrorCode::ChildrenNotAllowed);
});
```

## Exceptions

Every exception implements `Exceptions\ComponentException`:

| Exception | Parent | Thrown by |
| --- | --- | --- |
| `InvalidConfigurationException` | `InvalidArgumentException` | constructor items, schema definitions, unnormalizable values |
| `InvalidPropertyException` | `InvalidArgumentException` | property keys and values |
| `DuplicatePropertyException` | `InvalidPropertyException` | repeated keys |
| `MissingPropertyException` | `OutOfBoundsException` | `Component::require()` |
| `InvalidChildException` | `InvalidArgumentException` | cycles and excessive nesting during traversal |
| `InvalidComponentException` | `DomainException` | strict validation; exposes `result()` |

## Integration map for future packages

| Package | Uses |
| --- | --- |
| `gcego/core`, `gcego/blueprints` | `Component`, `ComponentDefinition`, `assertValid()` |
| `gcego/registry` | `normalize()`, `ComponentIdentity::stableKey()`, `ComponentDefinition::toArray()` |
| `gcego/runtime` | `ComponentWalker`, `TreeNode`, `RuntimeValue` |
| `gcego/chroma` | `ConfigurationNode`, `ConfigurationCollection` |
| `gcego/tables`, `gcego/forms`, `gcego/infolists`, `gcego/widgets`, `gcego/layouts`, `gcego/actions` | subclasses with `define()` and `shorthand()` |
| `gcego/cli`, `gcego/studio`, `gcego/testing` | `ComponentInspector`, `ValidationResult::toArray()` |

## Builder invariants

Components and their builders must be concrete, instantiable classes. The manager
checks that `builderClass()` extends `ComponentBuilder` before resolving it, that
container bindings target the requested component, and that the result has the
requested component type. Constructor adapters must preserve that invariant.
Nested builder item lists are limited to 32 levels; iterable values are streamed
in order, including entries with repeated keys.

## Fluent method names

Concrete builder methods are reserved names, including `schema`, `components`,
`child`, `label`, `set`, `setMany`, `setIfMissing`, `get`, `has`, `properties`,
`forget`, `onlyProperties`, `clearProperties`, `replaceProperties`, `with`,
`replaceChildren`, `prependChildren`, `clearChildren`, `filterChildren`,
`mapChildren`, `children`, `childCount`, `configure`, `replaceConfigurations`,
`clearConfigurations`, `tap`, `apply`, `when`, `unless`, `build`, `buildValidated`,
`validate`, `normalize`, `tree` and `inspect`. Nesting names append
children; use `set('child', $value)` or `set('components', $value)` when these are
property names in your component schema. `label()` uses the same constant/key
mapping as dynamic property setters and validates values through the schema.
Static `Component::section()`, `group()` and `item()` always construct the built-in
layout types; use `YourComponent::define()` to construct your own component class.

## Extending layout components

Extend `Layout\Section`, `Layout\Group`, `Layout\Item` or `Layout\Schema`
when building a specialized layout node. Sections, groups and items share the
abstract `Foundation\NamedComponent`, which owns their required name and positional
name shorthand. Start from `parent::defineSchema()` to preserve those rules;
items also inherit the leaf restriction. Set a distinct type with `withType()`
and add properties with `withProperties()`. Declare the extension through
`YourSection::define('dashboard')` and put it inside `Components::schema()`.
The ordinary custom builder hook, `builderClass()`, works for layout extensions.

## Reusable builder composition

```php
$panel = Component::card('account')
    ->setMany(['label' => 'Account', 'description' => 'Manage your profile'])
    ->when($canEdit,
        fn ($builder) => $builder->child(Component::button('edit')),
        fn ($builder) => $builder->child(Component::text('readonly')->content('Read only')),
    )
    ->prependChildren(Component::heading('title')->content('Account')->level(2))
    ->buildValidated();
```

Callbacks receive immutable builders; return the modified builder. `tap()` uses
the returned value, and `apply()` runs a list of such transformations in order.
`filterChildren()` and `mapChildren()` do not build lazy children; map returns a
component, builder or null. `get()` and `has()` inspect explicit properties only,
so `setIfMissing()` preserves an explicit null and can override a schema default.
`setMany()` accepts string keys and uses the last yielded value for duplicate keys.

## Shared family bases

Use `Foundation\NamedComponent` for a named component that is not tied to a UI
category. Use `Forms\FieldComponent` for a form control; `Schema\Field` is a
property-schema builder and does not represent a UI control. Concrete controls
and layout classes inherit shared family schemas. Extend the appropriate family,
call `parent::defineSchema()` and add a unique type plus only the differing fields.
See [responsibility ownership and migration](architecture.md#responsibility-ownership).

## Complete extension example

The [custom example](../examples/custom-component.php) resolves a declared custom
builder via the provider, uses fluent `Schema\Field` definitions, normalizes a
value object and attaches an allowed configuration node. Its classes are split
into matching `Components`, `Building`, `Values` and `Configurations` folders.
Run `php examples/run.php --custom` or follow the [usage guide](usage.md).
