<?php

declare(strict_types=1);

use Gcego\Components\Catalog\ComponentCatalog;
use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Tests\Fixtures\DashboardSection;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

it('builds every catalog entry through the manager and fluent factory', function (): void {
    $types = [];
    foreach (app(ComponentCatalog::class)->all() as $alias => $class) {
        $component = Component::$alias('example')->label('Example')->build()->assertValid();
        $types[] = $component->type();
        expect($component)->toBeInstanceOf($class)
            ->and($component->name())->toBe('example')
            ->and($component->normalize())->toBe(Components::component($alias, 'example')->label('Example')->build()->normalize())
            ->and($component->withChildren(Component::item('nested')->build())->validate()->isValid())->toBe($class::definition()->allowsChildren);
    }
    expect($types)->toHaveCount(84)
        ->and(array_unique($types))->toHaveCount(84);
});

it('validates typed fluent properties and unknown settings', function (): void {
    $field = Component::textInput('title')->required()->readOnly()->minLength(2)->maxLength(100)->helpText('Enter a title')->build()->assertValid();
    expect($field->get('readOnly'))->toBeTrue()
        ->and($field->get('helpText'))->toBe('Enter a title')
        ->and(Component::textInput('bad')->maxLength(-1)->build()->validate()->isValid())->toBeFalse()
        ->and(Component::heading('title')->level(7)->build()->validate()->isValid())->toBeFalse()
        ->and(Component::checkbox('enabled')->checked('yes')->build()->validate()->isValid())->toBeFalse()
        ->and(Component::form('edit')->method('EXEC')->build()->validate()->isValid())->toBeFalse()
        ->and(fn () => Component::textInput('title')->maxLenght(5))->toThrow(InvalidBuilderException::class);
});

it('composes mixed technology components through existing services', function (): void {
    $schema = Components::schema([
        Component::card('account')->components([
            Component::form('profile')->components([
                Component::emailInput('email')->required(),
                Component::select('role')->options(['admin' => 'Administrator']),
                Component::button('save')->label('Save')->buttonType('submit'),
            ]),
            Component::chart('visits')->chartType('line')->data([1, 2, 3]),
            Component::table('users')->data([])->components([
                Component::tableColumn('name')->field('name')->sortable(),
            ]),
        ]),
    ])->assertValid();

    expect($schema->tree()->count())->toBe(9)
        ->and($schema->normalize()['children'][0]['type'])->toBe('layout.card')
        ->and(Components::inspect($schema)->isValid())->toBeTrue();
});

it('supports custom aliases with scope isolation and container overrides', function (): void {
    $app = Container::getInstance();
    $catalog = $app->make(ComponentCatalog::class);
    $catalog->register('dashboardSection', DashboardSection::class);

    expect(Component::dashboardSection(name: 'dashboard')->collapsible()->build()->assertValid())->toBeInstanceOf(DashboardSection::class);
    $app->forgetScopedInstances();
    expect($app->make(ComponentCatalog::class))->not->toBe($catalog)
        ->and(fn () => Components::component('dashboardSection', 'dashboard'))->toThrow(InvalidConfigurationException::class);
});

it('rejects invalid conflicting and unknown aliases', function (): void {
    $catalog = app(ComponentCatalog::class);
    foreach (['textInput', 'TextInput', 'define', 'get', 'bad-alias', 'bad'."\n"] as $alias) {
        expect(fn () => $catalog->register($alias, DashboardSection::class))->toThrow(InvalidConfigurationException::class);
    }
    expect(fn () => $catalog->register('notComponent', stdClass::class))->toThrow(InvalidConfigurationException::class)
        ->and(fn () => Components::component('unknownType', 'name'))->toThrow(InvalidConfigurationException::class)
        ->and(fn () => Component::textInput())->toThrow(InvalidBuilderException::class)
        ->and(fn () => Component::textInput(42))->toThrow(InvalidBuilderException::class)
        ->and(fn () => Component::textInput(other: 'name'))->toThrow(InvalidBuilderException::class);
});

it('requires provider registration for catalog services and factories', function (): void {
    Facade::setFacadeApplication(new Application(dirname(__DIR__, 3)));
    expect(fn () => Component::textInput('name'))->toThrow(InvalidConfigurationException::class)
        ->and(fn () => new ComponentCatalog)->toThrow(InvalidConfigurationException::class);
});

it('validates configuration bounds without crashing on mistyped values', function (): void {
    foreach ([
        Component::textInput('title')->minLength(10)->maxLength(2),
        Component::numberInput('quantity')->min(10)->max(2),
        Component::rangeInput('range')->step(0),
        Component::numberInput('quantity')->value(INF),
        Component::numberInput('quantity')->min('wrong'),
        Component::map('location')->latitude(91),
        Component::map('location')->longitude(NAN),
        Component::pagination('pages')->pageSize(0),
        Component::grid('layout')->columns(0),
    ] as $builder) {
        expect($builder->build()->validate()->isValid())->toBeFalse();
    }

    expect(Component::numberInput('quantity')->min(-10)->max(20)->step(0.5)->value(2)->build()->validate()->isValid())->toBeTrue()
        ->and(Components::catalog())->toBe(app(ComponentCatalog::class));
});

it('reapplies provider extension callbacks after scope resets', function (): void {
    $app = Container::getInstance();
    $app->afterResolving(ComponentCatalog::class, static function (ComponentCatalog $catalog): void {
        $catalog->register('dashboardExtension', DashboardSection::class);
    });

    foreach (range(1, 2) as $scope) {
        expect(Components::component('dashboardExtension', 'dashboard')->build()->assertValid())->toBeInstanceOf(DashboardSection::class);
        $app->forgetScopedInstances();
    }
});
