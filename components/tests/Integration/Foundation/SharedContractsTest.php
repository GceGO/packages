<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Feedback\Message;
use Gcego\Components\Forms\ChoiceField;
use Gcego\Components\Forms\FieldComponent;
use Gcego\Components\Forms\Select;
use Gcego\Components\Foundation\NamedComponent;
use Gcego\Components\Layout\LayoutBlock;
use Gcego\Components\Layout\Row;
use Gcego\Components\Media\Image;
use Gcego\Components\Media\ImageComponent;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\Field;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

it('shares inherited contracts without changing sibling schema definitions', function (): void {
    $row = Component::row('row')->gap('md')->build()->assertValid();
    $custom = new class(new Property(Component::Name, 'custom')) extends Row
    {
        protected static function defineSchema(): ComponentDefinition
        {
            return parent::defineSchema()->withType('custom.row')
                ->withoutProperties('gap')
                ->withProperties(new PropertyDefinition('density', Type::string(), default: 'compact'));
        }
    };

    expect($custom::definition()->hasProperty('gap'))->toBeFalse()
        ->and($custom->get('density'))->toBe('compact')
        ->and($row::definition()->hasProperty('gap'))->toBeTrue()
        ->and($row::definition()->hasProperty('density'))->toBeFalse()
        ->and(Component::column('column')->gap('lg')->span(2)->validate()->isValid())->toBeTrue()
        ->and(Component::datePicker('date')->format('Y-m-d')->validate()->isValid())->toBeTrue()
        ->and(Component::video('video')->controls()->poster('/poster.png')->validate()->isValid())->toBeTrue();
});

it('keeps different domain objects and concrete renderer types distinct', function (): void {
    $control = Component::textInput('title')->build();
    $property = Field::string('title')->mustExist()->build();
    expect($control)->toBeInstanceOf(FieldComponent::class)
        ->and($control)->toBeInstanceOf(NamedComponent::class)
        ->and($property)->toBeInstanceOf(PropertyDefinition::class)
        ->and(Component::avatar('avatar')->build())->not->toBeInstanceOf(Image::class)
        ->and(Component::radio('choice')->build())->not->toBeInstanceOf(Select::class);
});

it('rejects abstract family bases as concrete factory targets', function (string $class): void {
    expect(fn () => Components::define($class))->toThrow(InvalidBuilderException::class)
        ->and(fn () => Components::catalog()->register('abstractExample', $class))->toThrow(InvalidConfigurationException::class);
})->with([
    NamedComponent::class,
    FieldComponent::class,
    LayoutBlock::class,
    Message::class,
    ImageComponent::class,
    ChoiceField::class,
]);
