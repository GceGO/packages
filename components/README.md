# gcego/components

The framework-independent foundation of **GCEGO Admin Studio**: a declarative,
strongly validated, immutable PHP component model.

Framework authors define a component once: its properties, defaults, allowed
children and shorthand. Application developers then declare only what they need:

```php
use Gcego\Components\Component;
use Gcego\Components\Property;

return new Component(
    new Property(Component::Name, 'dashboard'),
    new Property(Component::Label, 'Dashboard'),

    new Component(new Property(Component::Name, 'statistics')),
    new Component(new Property(Component::Name, 'activity')),
);
```

The package is pure PHP. It does not depend on Laravel, Livewire, Blade,
Alpine.js, Tailwind CSS or Eloquent. Rendering, registration and styling
belong to the packages built on top of it.

## Requirements

- PHP 8.3 or later
- No runtime Composer dependencies

## Installation

```bash
composer require gcego/components
```

Inside the GCEGO monorepo, add a path repository to the consuming `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/components" }
    ],
    "require": {
        "gcego/components": "*"
    }
}
```

## A concrete component in 20 lines

```php
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

final class Text extends Component
{
    public const string Searchable = 'searchable';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('tables.columns.text')
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true),
                new PropertyDefinition(self::Searchable, Type::bool(), default: false),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
```

Both declarations below produce the same normalized definition:

```php
new Text('name', 'Subject Name');

new Text(
    new Property(Text::Name, 'name'),
    new Property(Text::Label, 'Subject Name'),
);
```

Misspelled or mistyped configuration is reported precisely:

```php
(new Text('name', new Property('serchable', true)))->assertValid();
```

```text
Component validation failed with 1 error(s).

Unknown property "serchable" on component "tables.columns.text".

Expected: one of name, label, description, id, searchable
Received: "serchable"

Did you mean "searchable"?

Path: name
```

## Documentation

| Topic | |
| --- | --- |
| [Architecture](docs/architecture.md) | Design decisions, lifecycle, class map |
| [Components](docs/components.md) | Declaring, reading, copying, identity |
| [Properties](docs/properties.md) | Property values, types, definitions |
| [Composition](docs/composition.md) | Children, configuration nodes, trees, traversal |
| [Validation](docs/validation.md) | Collected and strict validation, error codes |
| [Normalization](docs/normalization.md) | The canonical output format |
| [Extension guide](docs/extension-guide.md) | Building packages on top of this one |
| [API reference](docs/api-reference.md) | Every public class and method |

## Development

```bash
composer test           # Pest test suite
composer analyse        # PHPStan, level max
composer format         # Laravel Pint
composer benchmark      # construction / lookup / traversal / validation / normalization timings
composer test-coverage  # requires Xdebug or PCOV
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
