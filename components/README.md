# gcego/morfaw

The Laravel component foundation of **GCEGO Admin Studio**: a declarative,
strongly validated, immutable PHP component model.

Framework authors define a component once: its properties, defaults, allowed
children and shorthand. Application developers then declare only what they need:

```php
use Gcego\Components\Component;
use Gcego\Components\Facades\Components;

return Components::schema([
    Component::section('dashboard')
        ->label('Dashboard')
        ->components([
            Component::group('metrics')
                ->child([
                    Component::item('statistics'),
                    Component::item('activity'),
                ]),
        ]),
]);
```

| Layer | Method | Purpose |
| --- | --- | --- |
| Root | `Components::schema([...])` | Wraps the layout in an immutable schema component. |
| Section | `Component::section('dashboard')` | Declares a major screen area. |
| Group | `Component::group('metrics')` | Groups related components. |
| Nesting | `->components([...])` / `->child([...])` | Appends children in declaration order. |
| Item | `Component::item('statistics')` | Declares a leaf component; children fail validation. |
| Collection | `Component::collect($components)` | Builds an iterable of components or builders into an ordered collection; null entries are skipped. |

`schema()` builds the nested builders into components. Call `->assertValid()` to
check the entire layout, or `->normalize()` for its canonical array. Sections,
groups and items require non-empty names. Builders remain immutable, and nesting
methods append children. Collections can be passed to either nesting method.

`ComponentServiceProvider` is the required initialization and discovery point.
It registers the manager, factories, validation, normalization, traversal and
inspection services in Laravel's container. Component convenience methods use
those same services. Rendering and styling belong to the packages built on top.

## Filament-style authoring

If you know Filament v4, you already know morfaw. Every catalog component starts
with `make()`, takes closures wherever it takes values, and shares Filament's
layout and visibility vocabulary:

```php
use Gcego\Components\Evaluation\Get;
use Gcego\Components\Forms\{Select, TextInput};
use Gcego\Components\Layout\{Schema, Section};

return Schema::make()->columns(['default' => 1, 'lg' => 2])->components([
    Section::make('account')
        ->label('Account')
        ->hiddenOn('view')
        ->schema([
            TextInput::make('title')->required()->maxLength(120),
            TextInput::make('slug')->disabled(fn (string $operation): bool => $operation === 'edit'),
            Select::make('type')->options(['person' => 'Person', 'business' => 'Business']),
            TextInput::make('company')
                ->visible(fn (Get $get): bool => $get('type') === 'business')
                ->columnSpanFull(),
        ]),
]);
```

| Filament v4 | morfaw | What morfaw adds |
| --- | --- | --- |
| `TextInput::make('email')` | the same | Builders are immutable, so a shared definition can never be changed by mistake somewhere else. |
| Closures with `$get`, `$set`, `$state`, `$record`, `$operation`, `$component`, typed injection | the same, through `$component->evaluateProperty('label', $context)` | A closure's result is checked against the property definition. A misspelled parameter fails with `Did you mean $record?`. |
| `configureUsing()` (with `during:` and `isImportant:`) | the same | Stored per application, not in static properties, so tests stay isolated and Octane keeps the configuration between requests. |
| Macros | `TextInput::macro('slug', fn () => $this->maxLength(64))` | Macros apply to their class and its subclasses only. |
| `columns()`, `columnSpan()`, `columnSpanFull()`, `columnStart()` | the same, including breakpoint arrays | Breakpoints and counts are validated with precise errors. |
| `hidden()`, `visible()`, `hiddenOn()`, `visibleOn()` | the same, read with `isHiddenIn($context)` | |
| Auto labels from field names | `displayLabel()` | |
| Validation at render time | `assertValid()`: the whole tree up front, with paths and "did you mean" hints | Normalization, tree queries and inspection, none of which Filament offers. |

Give a component its own defaults with the `setUp()` hook, Filament's
`setUp()` equivalent:

```php
protected static function setUp(ComponentBuilder $builder): ComponentBuilder
{
    return $builder->maxLength(255);
}
```

## The live engine

`Components::live()` brings any schema to life over state. Every package
built on morfaw (forms, tables, infolists, panels) gets the same reactive
behavior from one place:

```php
$live = Components::live($schema, $record->toArray(), ['operation' => 'edit', 'record' => $record]);

$live->isLive('type');              // true: a closure reads $get('type'); no ->live() or dependsOn needed
$changes = $live->set('type', 'business');
$changes->components;               // ['#0.account.type', '#0.account.company']: redraw only these
$live->dehydrate();                 // visible fields not marked dehydrated(false); hidden ones never leak
$live->toArray();                   // a JSON-safe snapshot for Livewire, Inertia or a mobile client
```

| What it does | How |
| --- | --- |
| Fills defaults | `->default('x')` or `->default(fn (string $operation) => ...)` for every missing state path, without overwriting supplied values. |
| Nests state | Fields use their name; `Group::make('address')->statePath('address')` nests its fields under `address.*`. |
| Discovers dependencies | Each closure runs while the state records its reads. `dependentsOf('type')`, `isLive()` and `livePaths()` read that graph. |
| Runs hooks | `->afterStateUpdated(fn (Set $set, $state, $old) => ...)` runs on `set()`. Values it sets are reported in `Changes::$statePaths`. |
| Diffs updates | `set()` resolves again and returns the components whose properties, visibility, label or state changed, appeared or disappeared. |
| Keeps hidden data private | Hidden components and the children of hidden containers are not evaluated and never dehydrated. |

A 201-component reactive form resolves in about 8 ms per update (`composer benchmark`).

## Extending other modules' schemas

Patches edit built schemas immutably, so one module can extend another's
form without copying it:

```php
// In the loyalty module's service provider:
Components::extend('customers.form', fn (SchemaPatch $patch) => $patch
    ->insertAfter('account.email', TextInput::make('loyalty_number'))
    ->modify('email', fn (ComponentBuilder $email) => $email->required())
    ->remove('legacy_code'));

// Where the customers module publishes its form:
return Components::extended('customers.form', $schema);
```

Selectors are name chains matched against the end of tree paths (`email`,
`account.email`, `email#1`). A selector that matches nothing fails with
`Did you mean "account.email"?` instead of silently doing nothing.

## Complete PHP example

Open [examples/full-usage.php](examples/full-usage.php) for a complete dashboard
with 49 components. It returns a validated schema and can be loaded directly from
a booted Laravel application. Run it locally with:

```bash
php examples/run.php
php examples/run.php --normalized
php examples/run.php --custom
```

The [usage guide](docs/usage.md) explains application integration, builder
composition, validation, tree queries and the custom-component example. Supporting
example classes live in their respective folders under `examples/`.

## Built-in builders

The package includes **84 component types** across layouts, forms, data display,
navigation, feedback, actions and media:

```php
Component::textInput('title')->required()->maxLength(100);
Component::select('role')->options(['admin' => 'Administrator']);
Component::button('save')->label('Save')->buttonType('submit');
Component::chart('visits')->chartType('line')->data([1, 2, 3]);
```

See the [component catalog](docs/component-catalog.md) for every factory,
property contract and extension registration. Consuming packages provide
rendering and browser/server behavior.

## Requirements

- PHP 8.3 or later
- Laravel 12 or 13 with `ComponentServiceProvider` registered
- Illuminate Container, Contracts and Support (installed by Composer)

## Installation

```bash
composer require gcego/morfaw
```

Inside the GCEGO monorepo, add a path repository to the consuming `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/components" }
    ],
    "require": {
        "gcego/morfaw": "*"
    }
}
```

## Laravel integration

Laravel discovers `Gcego\Components\ComponentServiceProvider` automatically.
The provider is mandatory. It registers a scoped manager, shared stateless
services and transient builders and trees, preserving application overrides.
Use `Gcego\Components\Facades\Components` for component creation and operations.
Laravel also discovers the `Components` facade alias. See
[Laravel integration](docs/laravel.md) for registration, facade usage, namespace
migration, customization and test setup.

## Defining a custom component

```php
use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\Field;

final class Text extends Component
{
    public const string Searchable = 'searchable';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('tables.columns.text')
            ->withFields([
                Field::string(self::Name)->mustExist(),
                Field::boolean(self::Searchable)->defaultsTo(false),
            ])
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
use Gcego\Components\Properties\Property;

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
| [Complete usage](docs/usage.md) | Runnable examples and application integration |
| [Component catalog](docs/component-catalog.md) | Factories, properties and custom registrations |
| [Architecture](docs/architecture.md) | Design decisions, lifecycle, class map |
| [Components](docs/components.md) | Declaring, reading, copying, identity |
| [Properties](docs/properties.md) | Property values, types, definitions |
| [Composition](docs/composition.md) | Children, configuration nodes, trees, traversal |
| [Validation](docs/validation.md) | Collected and strict validation, error codes |
| [Normalization](docs/normalization.md) | The canonical output format |
| [Extension guide](docs/extension-guide.md) | Building packages on top of this one |
| [Security model](docs/security.md) | Input boundaries, limits and release checks |
| [Laravel integration](docs/laravel.md) | Discovery, container services and overrides |
| [API reference](docs/api-reference.md) | Every public class and method |

## Development

```bash
composer check          # Validation, strict autoload checks, formatting, analysis and tests
composer test           # Pest test suite
composer analyse        # PHPStan, level max
composer format         # Laravel Pint
composer benchmark      # construction / lookup / traversal / validation / normalization timings
composer test-coverage  # requires Xdebug or PCOV
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
