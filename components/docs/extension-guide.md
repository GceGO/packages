# Extension guide

This guide is for authors of GCEGO packages (`gcego/tables`, `gcego/forms`,
`gcego/chroma`, ...) that build on `gcego/components`.

## 1. Extend `Component`

```php
namespace Gcego\Tables\Columns;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

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

- `define()` runs once per class, and its result is cached by `definition()`.
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

A component that needs an unusual constructor may still define one, as long as
it delegates to `parent::__construct(...)` with standard items:

```php
public function __construct(string $name, mixed ...$items)
{
    parent::__construct(new Property(self::Name, $name), ...$items);
}
```

Prefer `shorthand()` where possible, since it needs no constructor code.

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
3. key per-occurrence state by `TreeNode::identity()->stableKey()`.

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
