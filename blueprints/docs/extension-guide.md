# Extension guide

The fixtures in `tests/Fixtures` are working examples of everything below.

## A part

Any component becomes a part by implementing `BlueprintPart`. This is the
`ExampleTable` fixture, standing in for a future `gcego/tables` table:

```php
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

final class ExampleTable extends Component implements BlueprintPart
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.ExampleTable')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true))
            ->withAllowedChildren([ExampleColumn::class, ExampleAction::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Table);
    }
}
```

`ExampleAction`, `ExampleWidget` and `ReportChart` (role `reports.chart`)
return `BlueprintRole::multiple(...)`.

Guidelines:

- **Spell standard roles with the constants** (`BlueprintRole::Table`, ...) and
  use their conventional cardinality.
- **Namespace new roles** (`reports.chart`, `scheduling.calendar`) to avoid
  collisions.
- **Return the same role from every instance of a class.** Parts of one role
  that disagree on cardinality fail validation.
- **The part's children are its own business.** A row action inside a table
  is not a blueprint action.

## A container

Implement `PartContainer` (which extends `BlueprintPart`) on layout parts whose
children are themselves parts. This is the `ExampleSection` fixture:

```php
final class ExampleSection extends Component implements PartContainer
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.ExampleSection')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true))
            ->withAllowedChildren([BlueprintPart::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple(BlueprintRole::Layout);
    }
}
```

Widgets inside sections then count as the blueprint's widgets, with paths such
as `dashboard.overview.enrolments`.

## A stricter blueprint

Higher-level packages subclass `Blueprint` and override `definePolicy()` (see
the `ResourceBlueprint` fixture and [composition](composition.md#policies)).
They may also extend `define()` to add their own metadata properties:

```php
protected static function defineSchema(): ComponentDefinition
{
    return parent::defineSchema()
        ->withType('modules.resource')
        ->withProperties(new PropertyDefinition('navigationGroup', Type::string()));
}
```

`compositionPolicy()`, `part()`, `parts()`, `withPart()` and the other
blueprint methods are `final`, which protects the invariants.

## Styling and other configuration

Pass `ConfigurationNode` objects, such as a future `Tailwind` node (the
`ExampleStyle` fixture stands in for one), directly to the constructor. They
are available through `configurations()` and normalized as configuration.
They are never parts.

## Consuming blueprints (registry, runtime)

Depend on `BlueprintInterface` and `BlueprintResolver`, not on `Blueprint` or
`BlueprintCollection`:

```php
function render(BlueprintResolver $blueprints, string $name): void
{
    $blueprint = $blueprints->resolve($name);
    $blueprint->assertValid();

    foreach ($blueprint->parts() as $part) {
        // dispatch on $part->blueprintRole()->name
    }
}
```

## Testing a custom part

```php
it('is a single table part', function (): void {
    $blueprint = new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b'));

    expect((new BlueprintValidator)->validate($blueprint)->first()?->code)
        ->toBe(BlueprintErrorCode::DuplicateSingleton->value);
});
```
