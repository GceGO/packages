# Components

## Declaring a component

```php
use Gcego\Components\Component;
use Gcego\Components\Property;

$component = new Component(
    new Property(Component::Name, 'example'),
    new Property(Component::Label, 'Example Component'),
);
```

The constructor accepts any number of items: properties, child components
(`ComponentInterface`) and configuration nodes (`ConfigurationNode`). Items can
appear in any order. Children and configuration nodes keep their declaration
order.

Anything else throws `InvalidConfigurationException`, unless the class declares
[shorthand](#shorthand-constructors) values:

```php
new Component('example');
// Unsupported constructor argument #1 (string "example") for Gcego\Components\Component.
//
// Component accepts no positional shorthand values. Pass a Property, a child component or a configuration node instead.
```

## Built-in property constants

| Constant | Value | Type | Notes |
| --- | --- | --- | --- |
| `Component::Name` | `'name'` | `string` | must not be empty; used for tree paths |
| `Component::Label` | `'label'` | `string` | |
| `Component::Description` | `'description'` | `string` | |
| `Component::Id` | `'id'` | `string` | explicit stable id, must match `Component::IdPattern` |

Subclasses add their own constants, such as `public const string Searchable = 'searchable';`.

## Reading properties

```php
$component->get(Component::Label);              // explicit value, else default, else null
$component->get(Component::Label, 'Untitled');  // ... else the given fallback
$component->require(Component::Label);          // throws MissingPropertyException when absent
$component->has(Component::Label);              // explicit value or default exists
$component->hasExplicit(Component::Label);      // explicitly declared
$component->properties();                       // PropertyCollection of explicit values
$component->defaults();                         // schema defaults
$component->effectiveProperties();              // defaults merged with explicit values, sorted by key
$component->name();                             // ?string
$component->id();                               // ?string, explicit id only
$component->type();                             // schema type identifier
```

An explicit `new Property('perPage', null)` is not the same as leaving
`perPage` out. The explicit `null` wins over the default, and it is valid only
when the property definition is `nullable`.

## Children

```php
$component->children();      // ComponentCollection
$component->childCount();    // int
$component->hasChildren();   // bool
$component->configurations(); // ConfigurationCollection
$component->tree();          // ComponentTree
```

See [composition](composition.md).

## Validation and normalization

```php
$component->validate();     // ValidationResult, never throws, memoized
$component->assertValid();  // returns $component or throws InvalidComponentException
$component->normalize();    // canonical array
```

## Copies

Components are immutable. Copy methods return a new instance of the same
concrete class and leave the original untouched:

```php
$updated = $component->withProperty(new Property(Component::Label, 'Updated'));
$fewer = $component->withoutProperty(Component::Label);
$replaced = $component->withChildren($a, $b);
$appended = $component->withAppendedChildren($c);
$styled = $component->withConfigurations($style);
```

Copies go through the same structural rules as constructors, and the copy is
validated independently of the original.

## Shorthand constructors

A subclass lists, in order, the property keys that leading positional values
are assigned to:

```php
protected static function shorthand(): array
{
    return [self::Name, self::Label];
}
```

Then `new Text('name', 'Subject Name')` is exactly
`new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'))`.
Shorthand values become ordinary `Property` objects, so they go through the same
value checks, duplicate detection, validation and normalization.

Rules:

- Shorthand values must come before every other item.
- You may pass fewer values than declared keys, but not more.
- Each component chooses its own signature (`[self::Name]`, `[self::Model]`, ...)
  or none at all.

## Identity

| Concept | API |
| --- | --- |
| Component type | `$component->type()`, set by `ComponentDefinition::$type` |
| Explicit id | `new Property(Component::Id, 'main-table')`, read with `$component->id()` |
| Occurrence | `TreeNode::$path`, `TreeNode::identity()` |

Ids are optional. Declare one only when a component needs stable identity across
stateful interactions. Ids must be unique within a tree.
