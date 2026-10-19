# Normalization

`ComponentNormalizer` turns a component tree into a canonical, deterministic
PHP array for registries, caches, runtimes and tooling.

```php
$normalized = $component->normalize();
// or
$normalized = (new ComponentNormalizer(includeSchema: true))->normalize($component);
```

## Shape

```php
[
    'type' => 'fixtures.parent',          // schema type identifier
    'class' => ParentComponent::class,    // concrete PHP class
    'id' => 'subjects-table',             // explicit id or null; never generated
    'path' => 'subjects',                 // deterministic occurrence path
    'properties' => [                     // defaults merged with explicit values, sorted by key
        'id' => 'subjects-table',
        'model' => FakeModel::class,
        'name' => 'subjects',
    ],
    'configurations' => [                 // declaration order
        ['class' => Style::class, 'data' => ['classes' => 'p-4']],
    ],
    'children' => [ /* same shape, declaration order */ ],
    // 'schema' => ComponentDefinition::toArray(), only with includeSchema: true
]
```

## Guarantees

- **Deterministic.** Properties are sorted by key, and children and configuration
  nodes keep their declaration order. Equal declarations give equal output, so
  `new Text('name', 'Subject Name')` and its advanced `Property` form normalize
  identically.
- **Effective values.** Defaults are included. Each property definition's
  `normalizer` closure runs on its value.
- **No leaks.** Values are converted as follows:

  | Value | Normalized as |
  | --- | --- |
  | `null`, scalars, enum cases | unchanged |
  | arrays | converted recursively, keys preserved |
  | `ValueObject` | its `normalize()` result, converted recursively |
  | `RuntimeValue` | a `Normalization\RuntimePlaceholder` holding only the class name |

  Any other value, for example a `ValueObject` that returns a service object,
  throws `InvalidConfigurationException` naming the path and property.

## Not JSON

The output keeps enum cases and `RuntimePlaceholder` objects, because those are
unambiguous. Encoding them for a transport (JSON, a Livewire snapshot, a cache)
is the job of the transport layer. Use `normalizeValue()` to convert individual
values with the same rules:

```php
(new ComponentNormalizer)->normalizeValue(['price' => new Money(500, 'XAF')]);
// ['price' => ['amount' => 500, 'currency' => 'XAF']]
```
