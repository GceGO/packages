<?php

declare(strict_types=1);

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Forms\FieldComponent;
use Gcego\Components\Forms\Select;
use Gcego\Components\Forms\TextInput;
use Gcego\Components\Layout\Schema;
use Gcego\Components\Layout\Section;
use Gcego\Components\Tests\Fixtures\PresetInput;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

it('starts Filament-style builders with make() and builds them when nested', function (): void {
    $schema = Schema::make()->columns(2)->components([
        Section::make('profile')->schema([
            TextInput::make('email')->label('Email')->required(),
        ]),
    ])->buildValidated();

    $input = $schema->children()->first()?->children()->first();

    expect($schema->get('columns'))->toBe(2)
        ->and($input)->toBeInstanceOf(TextInput::class)
        ->and($input?->get('required'))->toBeTrue();
});

it('runs the setUp() hook before configureUsing() modifiers and the caller\'s chain', function (): void {
    PresetInput::configureUsing(fn (ComponentBuilder $input): ComponentBuilder => $input->placeholder('configured'));

    $input = PresetInput::make('title')->maxLength(80)->build();

    expect($input->get('placeholder'))->toBe('configured')
        ->and($input->get('maxLength'))->toBe(80);
});

it('applies configureUsing() to subclasses only, with important modifiers last', function (): void {
    FieldComponent::configureUsing(fn (ComponentBuilder $field): ComponentBuilder => $field->helpText('important'), isImportant: true);
    FieldComponent::configureUsing(fn (ComponentBuilder $field): ComponentBuilder => $field->helpText('normal')->required());

    expect(TextInput::make('name')->build()->get('helpText'))->toBe('important')
        ->and(Select::make('role')->build()->get('required'))->toBeTrue()
        ->and(Section::make('profile')->build()->has('helpText'))->toBeFalse();
});

it('limits a configureUsing() modifier to its during callback and returns the callback result', function (): void {
    $inside = TextInput::configureUsing(
        fn (ComponentBuilder $input): ComponentBuilder => $input->disabled(),
        during: fn (): bool => TextInput::make('inside')->build()->get('disabled'),
    );

    expect($inside)->toBeTrue()
        ->and(TextInput::make('outside')->build()->get('disabled'))->toBeNull();
});

it('rejects configureUsing() modifiers that do not return the builder', function (): void {
    TextInput::configureUsing(function (ComponentBuilder $input): void {
        $input->required();
    });

    expect(fn () => TextInput::make('name'))->toThrow(InvalidBuilderException::class, 'Modifiers must return the configured builder');
});

it('keeps configuration and macros inside the application that registered them', function (): void {
    TextInput::configureUsing(fn (ComponentBuilder $input): ComponentBuilder => $input->required());
    TextInput::macro('slug', fn (): ComponentBuilder => $this->maxLength(64));

    $app = new Application(dirname(__DIR__, 3));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    $provider = new ComponentServiceProvider($app);
    $app->addDeferredServices(array_fill_keys($provider->provides(), ComponentServiceProvider::class));

    expect(TextInput::make('name')->build()->has('required'))->toBeFalse()
        ->and(TextInput::hasMacro('slug'))->toBeFalse();
});

it('adds macros to a class and its subclasses, bound to the builder', function (): void {
    TextInput::macro('slug', fn (int $length = 64): ComponentBuilder => $this->maxLength($length)->helpText('Lowercase, dashes only.'));

    $preset = PresetInput::make('handle')->slug(32)->build();

    expect($preset->get('maxLength'))->toBe(32)
        ->and($preset->get('helpText'))->toBe('Lowercase, dashes only.')
        ->and(TextInput::hasMacro('slug'))->toBeTrue()
        ->and(Select::hasMacro('slug'))->toBeFalse()
        ->and(fn () => Select::make('role')->slug())->toThrow(InvalidBuilderException::class, 'has no "slug" setting');
});

it('rejects macros that do not return the builder', function (): void {
    TextInput::macro('broken', fn (): string => 'not a builder');

    expect(fn () => TextInput::make('name')->broken())->toThrow(InvalidBuilderException::class, 'Macros must return the builder');
});

it('keeps make() builders immutable', function (): void {
    $base = TextInput::make('name');
    $required = $base->required();

    expect($base->build()->has('required'))->toBeFalse()
        ->and($required->build()->get('required'))->toBeTrue();
});
