# gcego/blueprints

A **Blueprint** composes GCEGO components into one feature definition, such
as "subject management". It holds the feature's metadata and its parts (a
data source, a table, a form, actions, widgets), each classified by **role**.

```php
use Gcego\Blueprints\Blueprint;

return new Blueprint(
    'subjects',

    new Table(
        new Text('name', 'Subject Name'),
        new Text('code', 'Code'),
    ),

    new Form(
        new TextInput('name', 'Subject Name'),
    ),

    new Create,
    new Edit,
);
```

`Table`, `Text`, `Form`, `TextInput`, `Create` and `Edit` belong to future
GCEGO packages; this package's tests use fixtures in their place. The title
(`Subjects`) and slug (`subjects`) are derived from the name, and no role is
mandatory.

Blueprints are plain PHP. They do not render, query, route or authorize
anything. They use the Laravel services registered by
`Gcego\Components\ComponentServiceProvider` and do not depend on `gcego/core`.

## Requirements and installation

- PHP 8.3+
- `gcego/morfaw` ^0.1 with `ComponentServiceProvider` registered

```bash
composer require gcego/blueprints
```

Inside the monorepo, `packages/blueprints/composer.json` already points a
path repository at `../components`.

## Metadata

```php
new Blueprint(
    new Property(Blueprint::Name, 'subjects'),             // required identity
    new Property(Blueprint::Title, 'Subject Management'),  // default: "Subjects"
    new Property(Blueprint::Description, 'Manage academic subjects'),
    new Property(Blueprint::Icon, 'academic-cap'),
    new Property(Blueprint::Slug, 'subject-management'),   // default: "subjects"
);

$blueprint->metadata()->title;   // typed, read-only view over the properties
$blueprint->identity()->key();   // "subjects"
```

## Parts and roles

A part is any component implementing `Contracts\BlueprintPart`. It declares its role:

```php
public function blueprintRole(): BlueprintRole
{
    return BlueprintRole::single(BlueprintRole::Table);     // at most one per blueprint
    // or BlueprintRole::multiple(BlueprintRole::Action);   // any number
}
```

Looking parts up:

```php
$blueprint->part('table');      // ?BlueprintPart; throws if "table" allows several parts
$blueprint->requirePart('form');// throws MissingBlueprintPartException when absent
$blueprint->parts('action');    // BlueprintPartCollection, never just the first one
$blueprint->hasPart('form');
$blueprint->roles();            // ['table', 'form', 'action']
```

## Validation, normalization, inspection

```php
$blueprint->assertValid();                                 // component schema + composition rules
(new BlueprintValidator)->validate($blueprint);            // structured errors: code, role, path
(new BlueprintNormalizer)->normalize($blueprint);          // deterministic array for registries/runtimes
(new BlueprintInspector)->inspect($blueprint)->toArray();  // tooling snapshot
```

## Immutable customization

```php
$custom = $blueprint
    ->withProperty(new Property(Blueprint::Title, 'Academic Subjects'))
    ->withPart(new Table(...))       // replaces the existing table, keeping its position
    ->withoutRole('action');
```

## Documentation

| | |
| --- | --- |
| [Architecture](docs/architecture.md) | Relationship to components and core, design decisions, boundaries |
| [Composition](docs/composition.md) | Constructors, roles, cardinality, containers, policies, lookup |
| [Metadata](docs/metadata.md) | Properties, defaults, identity, naming rules |
| [Validation](docs/validation.md) | Error codes, structured and strict validation |
| [Normalization](docs/normalization.md) | The normalized shape |
| [Extension guide](docs/extension-guide.md) | Writing parts, containers and stricter blueprints |
| [API reference](docs/api-reference.md) | Every public class and method |

## Development

```bash
composer test       # Pest
composer analyse    # PHPStan, level max
composer format     # Laravel Pint
composer benchmark  # construction, lookup, validation, normalization
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
