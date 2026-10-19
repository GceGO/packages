# Properties

## `Property`

```php
new Property(Component::Name, 'subjects');
```

`Property` is a `final readonly` value object with public `$key` and `$value`.

**Keys** must match `Property::KeyPattern`: a letter or underscore, followed
by letters, digits, `_`, `.` or `-`, at most 128 characters. Other keys throw
`InvalidPropertyException`.

**Supported values:**

| Value | Notes |
| --- | --- |
| `null`, `bool`, `int`, `float`, `string` | class strings are plain strings |
| arrays | of supported values, nested at most 32 levels; copied by value |
| enum cases | backed and pure enums |
| `Contracts\ValueObject` | immutable objects that can `normalize()` themselves |
| `Contracts\RuntimeValue` | opaque runtime-only values, such as a wrapped callback |

Anything else throws `InvalidPropertyException` and names the offending location:

```php
new Property('options', ['nested' => [new stdClass]]);
// Unsupported value for property "options": value[nested][0] is stdClass. ...
```

Closures are rejected. They are never serialized or evaluated by this package.
If a future package needs a callable, it wraps it explicitly:

```php
final readonly class Callback implements RuntimeValue
{
    public function __construct(public Closure $callback) {}
}
```

Normalization replaces runtime values with a `RuntimePlaceholder` that carries
only the class name.

## `PropertyDefinition`

```php
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

new PropertyDefinition(
    name: 'perPage',
    type: Type::int(),                 // or a union: [Type::int(), Type::string()]
    required: false,
    nullable: true,                    // allow an explicit null
    default: 10,                       // omit for "no default"; null is a real default
    allowedValues: null,               // strict whitelist of scalars or enum cases
    rule: fn (mixed $value): bool => is_int($value) && $value > 0,
    ruleDescription: 'integer greater than zero',
    normalizer: null,                  // fn (mixed $value): mixed, applied during normalization only
    description: 'Rows per page',
);
```

Definitions are checked when they are built:

- A required property cannot also declare a default.
- Defaults must satisfy the definition's own types, allowed values and rule.
- The name must be a valid property key.

Any violation throws `InvalidConfigurationException`, so schema mistakes surface
the first time the definition is built.

Checks run in this order, and each property reports at most one error:

1. presence (`required`)
2. type (`nullable` allows `null`)
3. allowed values
4. custom rule

## `Type`

| Factory | Accepts |
| --- | --- |
| `Type::string()` | strings |
| `Type::int()` | integers (no numeric strings, no floats) |
| `Type::float()` | floats and integers |
| `Type::bool()` | booleans |
| `Type::array()` | any array |
| `Type::list()` | arrays with sequential keys from zero |
| `Type::mixed()` | any supported property value |
| `Type::enum(Status::class)` | cases of that enum, not their backing values |
| `Type::classString(Model::class)` | names of existing classes or interfaces that are `Model` or a subtype; the parent is optional |
| `Type::instanceOf(Money::class)` | instances of the class, typically a `ValueObject` |

Class-string checks may autoload the named class but never instantiate it.

## Absent vs. null

| Declaration | `get()` | `hasExplicit()` | Validation |
| --- | --- | --- | --- |
| not declared, no default | fallback (`null`) | `false` | fails if `required` |
| not declared, default `10` | `10` | `false` | passes |
| `new Property('perPage', null)` | `null` | `true` | passes only if `nullable` |
