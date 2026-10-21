<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Inspection\ComponentInspector;
use Gcego\Components\Normalization\RuntimePlaceholder;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Tests\Fixtures\Callback;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Status;
use Gcego\Components\Tests\Fixtures\Style;
use Illuminate\Container\Container;

it('inspects a component', function (): void {
    $inspection = (Container::getInstance()->make(ComponentInspector::class))->inspect(new ParentComponent(
        new Property(Component::Name, 'table'),
        new ChildComponent('a'),
        new Style('p-4'),
    ));

    expect($inspection->class)->toBe(ParentComponent::class)
        ->and($inspection->type)->toBe('fixtures.parent')
        ->and($inspection->path)->toBe('table')
        ->and($inspection->depth)->toBe(0)
        ->and($inspection->supportedProperties)->toBe(['name', 'label', 'description', 'id', 'model'])
        ->and($inspection->requiredProperties)->toBe([])
        ->and($inspection->allowsUnknownProperties)->toBeFalse()
        ->and($inspection->explicitProperties)->toBe(['name' => 'table'])
        ->and($inspection->childCount)->toBe(1)
        ->and($inspection->childrenAllowed)->toBeTrue()
        ->and($inspection->allowedChildren)->toBe([ExampleComponent::class, ChildComponent::class])
        ->and($inspection->minChildren)->toBe(1)
        ->and($inspection->maxChildren)->toBe(3)
        ->and($inspection->configurationClasses)->toBe([Style::class])
        ->and($inspection->isValid())->toBeTrue();
});

it('reports defaults and effective properties without leaking runtime values', function (): void {
    $inspection = (Container::getInstance()->make(ComponentInspector::class))->inspect(new ExampleComponent(
        'name',
        new Property(ExampleComponent::Formatter, new Callback(fn (): int => 1)),
    ));

    expect($inspection->defaults)->toHaveKey('status', Status::Draft)
        ->and($inspection->effectiveProperties)->toHaveKey('searchable', false)
        ->and($inspection->explicitProperties['formatter'])->toEqual(new RuntimePlaceholder(Callback::class));
});

it('includes subtree validation errors for a single inspection', function (): void {
    $inspection = (Container::getInstance()->make(ComponentInspector::class))->inspect(new Component(new ChildComponent));

    expect($inspection->isValid())->toBeFalse()
        ->and($inspection->errors)->toHaveCount(1)
        ->and($inspection->toArray()['valid'])->toBeFalse();
});

it('inspects every occurrence of a tree with its own errors', function (): void {
    $inspections = (Container::getInstance()->make(ComponentInspector::class))->inspectTree(new Component(
        new Property(Component::Name, 'root'),
        new ChildComponent,
        new ChildComponent('ok'),
    ));

    expect(array_map(fn ($inspection): string => $inspection->path, $inspections))->toBe(['root', 'root.#0', 'root.ok'])
        ->and($inspections[0]->isValid())->toBeTrue()
        ->and($inspections[1]->isValid())->toBeFalse()
        ->and($inspections[2]->isValid())->toBeTrue()
        ->and($inspections[1]->depth)->toBe(1);
});

it('exports structured arrays for tooling', function (): void {
    $array = (Container::getInstance()->make(ComponentInspector::class))->inspect(new ChildComponent)->toArray();

    expect(array_keys($array))->toBe([
        'class', 'type', 'id', 'path', 'depth', 'supportedProperties', 'requiredProperties',
        'allowsUnknownProperties', 'explicitProperties', 'effectiveProperties', 'defaults',
        'childCount', 'childrenAllowed', 'allowedChildren', 'minChildren', 'maxChildren',
        'configurationClasses', 'valid', 'errors',
    ])
        ->and($array['errors'])->toHaveCount(1);
});

it('uses schema normalizers consistently for explicit effective and default values', function (): void {
    $component = new class(new Property(Component::Label, '  Explicit  ')) extends Component
    {
        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()->withProperties(new PropertyDefinition(
                self::Label,
                Type::string(),
                default: '  Default  ',
                normalizer: static fn (string $value): string => trim($value),
            ));
        }
    };
    $inspection = (Container::getInstance()->make(ComponentInspector::class))->inspect($component);

    expect($inspection->explicitProperties['label'])->toBe('Explicit')
        ->and($inspection->effectiveProperties)->toBe($component->normalize()['properties'])
        ->and($inspection->defaults['label'])->toBe('Default');
});

it('keeps mistyped values inspectable without invoking typed property normalizers', function (): void {
    $component = new class(new Property(Component::Label, ['invalid'])) extends Component
    {
        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()->withProperties(new PropertyDefinition(
                self::Label,
                Type::string(),
                normalizer: static fn (string $value): string => trim($value),
            ));
        }
    };
    $inspection = (Container::getInstance()->make(ComponentInspector::class))->inspect($component);

    expect($inspection->isValid())->toBeFalse()
        ->and($inspection->errors)->toHaveCount(1)
        ->and($inspection->effectiveProperties['label'])->toBe(['invalid'])
        ->and($inspection->explicitProperties['label'])->toBe(['invalid']);
});
