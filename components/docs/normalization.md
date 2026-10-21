# Normalization

`ComponentNormalizer` turns a component tree into a canonical, deterministic
PHP array for registries, caches, runtimes and tooling.

```php
$normalized = $component->normalize();
// or
$normalized = app()->make(ComponentNormalizer::class, ['includeSchema' => true])->normalize($component);
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
  `normalizer` closure runs on its non-runtime value. Inspection uses this same pipeline
  for explicit properties, effective properties and defaults. A normalizer runs
  only when the value matches its declared type, so invalid values remain
  inspectable without calling typed closures with incompatible input. Normalizers
  should be pure functions and handle every value allowed by their schema.
- **Runtime opacity.** Values are converted as follows:

  | Value | Normalized as |
  | --- | --- |
  | `null`, scalars, enum cases | unchanged |
  | arrays | converted recursively, keys preserved |
  | `ValueObject` | its `normalize()` result, converted recursively |
  | `RuntimeValue` | a `Normalization\RuntimePlaceholder` holding only the class name |

  When an object implements both `RuntimeValue` and `ValueObject`, the runtime
  contract takes precedence and its `normalize()` method is never invoked.
  Direct runtime values also bypass property normalizer closures.

  Included schema defaults and direct schema `toArray()` exports also pass
  through safe value normalization. Their
  values describe the declared defaults before property normalizer closures.

  Any other value, for example a `ValueObject` that returns a service object,
  throws `InvalidConfigurationException` naming the path and property.

## Not JSON

The output keeps enum cases and `RuntimePlaceholder` objects, because those are
unambiguous. Encoding them for a transport (JSON, a Livewire snapshot, a cache)
is the job of the transport layer. Use `normalizeValue()` to convert individual
values with the same rules:

```php
app(ComponentNormalizer::class)->normalizeValue(['price' => new Money(500, 'XAF')]);
// ['price' => ['amount' => 500, 'currency' => 'XAF']]
```

## Class metadata

Named class metadata keeps its fully qualified PHP name. Anonymous class labels
omit PHP's NUL-delimited source path and line suffix in normalized output,
inspection snapshots, type descriptions and validation diagnostics. Such labels
are diagnostic identifiers, not autoloadable class strings or identity keys.
Internal type checks still use the original classes.

Scalar property values are preserved as declared. The normalizer does not redact
arbitrary application strings or sanitize user-provided data for an HTML context.

## Validated export example

```php
$schema = require app_path('Ui/admin.php');
$schema->assertValid();
$definition = Components::normalize($schema);
```

The full [usage example](usage.md) uses scalar/array values. Its CLI runner can
encode that sample with `--normalized`. The custom example's `Money` value object
becomes an array, and its `Padding` configuration becomes class metadata plus
normalized data. Neither example executes form actions or installs rendering.

The normalized root is a `schema` node with the same canonical shape as every
child. Layout declaration syntax and normalized output serve different roles;
factory methods do not flatten the transport into a different ad hoc structure.
