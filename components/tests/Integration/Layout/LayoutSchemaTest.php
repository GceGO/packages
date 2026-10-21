<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Layout\Schema;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Tests\Fixtures\DashboardSection;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\Style;

it('builds the documented layout and normalizes its hierarchy', function (): void {
    $schema = Components::schema([
        Component::section('dashboard')->label('Dashboard')->components([
            Component::group('metrics')->child([
                Component::item('statistics'),
                Component::item('activity'),
            ]),
        ]),
    ])->assertValid();

    $output = $schema->normalize();
    expect($output['type'])->toBe('schema');
    $section = $schema->children()->first();
    expect($section?->type())->toBe('section')
        ->and($section?->get(Component::Label))->toBe('Dashboard');
    $group = $section?->children()->first();
    expect($group?->type())->toBe('group')
        ->and($group?->children()->first()?->type())->toBe('item')
        ->and($output['children'][0]['children'][0]['children'][1]['properties']['name'])->toBe('activity');
});

it('collects dynamic builders and skips conditional nulls without mutating builders', function (): void {
    $group = Component::group('metrics');
    $items = Component::collect((function (): Generator {
        yield Component::item('statistics');
        yield null;
        yield Component::item('activity')->build();
    })());

    $schema = Components::schema([$group->child($items)->components(Component::item('extra'))]);
    expect($items)->toHaveCount(2)
        ->and($group->build()->childCount())->toBe(0)
        ->and($schema->children()->first()?->childCount())->toBe(3)
        ->and(Components::schema([])->assertValid()->childCount())->toBe(0);
});

it('rejects unnamed layout nodes and children on leaf items during validation', function (): void {
    expect(Components::schema([Component::section('')])->validate()->isValid())->toBeFalse();
    expect(fn () => Components::schema([
        Component::item('statistics')->child(Component::item('nested')),
    ])->assertValid())->toThrow(InvalidComponentException::class);
});

it('rejects non-child values at every layout entry point', function (string $entry, string $kind): void {
    $value = match ($kind) {
        'property' => new Property(Component::Label, 'injected'),
        'configuration' => new Style('hidden'),
        'scalar' => 'invalid',
    };
    $values = (function () use ($value): Generator {
        yield [[$value]];
    })();

    expect(fn () => match ($entry) {
        'schema' => Components::schema($values),
        'collect' => Component::collect($values),
        'components' => Component::section('dashboard')->components($values),
        'child' => Component::group('metrics')->child($values),
    })->toThrow(InvalidBuilderException::class, 'Child lists accept only');
})->with(['schema', 'collect', 'components', 'child'])->with(['property', 'configuration', 'scalar']);

it('keeps custom components and all runtime services usable inside layouts', function (): void {
    $custom = ExampleComponent::define('custom')->label('Custom');
    $schema = Components::schema([Component::section('dashboard')->components([$custom])])->assertValid();

    expect($schema)->toBeInstanceOf(Schema::class)
        ->and($schema->children()->first()?->children()->first())->toBeInstanceOf(ExampleComponent::class)
        ->and(Components::normalize($schema))->toBe($schema->normalize())
        ->and(Components::tree($schema)->root())->toBe($schema)
        ->and(Components::inspect($schema)->toArray())->toBeArray();
});

it('preserves custom label mapping and schema validation', function (): void {
    $component = new class extends Component
    {
        public const string Label = 'caption';

        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()->withProperties(new PropertyDefinition(self::Label, Type::string()));
        }
    };

    $built = $component::define()->label('Caption')->build()->assertValid();
    expect($built->get('caption'))->toBe('Caption');

    $withoutLabel = new class extends Component
    {
        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()->withoutProperties(self::Label);
        }
    };
    expect(fn () => $withoutLabel::define()->label('Label'))->toThrow(InvalidBuilderException::class);
    expect(Component::item('typed')->label(42)->build()->validate()->isValid())->toBeFalse();
});

it('bounds recursive child lists at the layout boundary', function (): void {
    $children = [];
    $children[] = &$children;

    expect(fn () => Components::schema($children))->toThrow(InvalidBuilderException::class, 'maximum nesting depth');
});

it('supports layout extensions with inherited naming and custom settings', function (): void {
    $section = DashboardSection::define('dashboard')->collapsible()->components([
        Component::group('metrics')->child(Component::item('statistics')),
    ]);
    $schema = Components::schema([$section])->assertValid();
    $built = $schema->children()->first();

    expect($built)->toBeInstanceOf(DashboardSection::class)
        ->and($built->type())->toBe('dashboard.section')
        ->and($built->name())->toBe('dashboard')
        ->and($built->get('collapsible'))->toBeTrue()
        ->and(DashboardSection::define('')->build()->validate()->isValid())->toBeFalse();
});
