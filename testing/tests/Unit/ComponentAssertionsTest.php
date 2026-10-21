<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Components\Validation\ErrorCode;
use Gcego\Forms\Fields\TextInput;
use Gcego\Testing\GcegoTest;

it('passes the documented component example', function (): void {
    GcegoTest::component(new TextInput('name', 'Name'))
        ->assertValid()
        ->assertName('name')
        ->assertLabel('Name')
        ->assertType(TextInput::class);
});

it('reports what is wrong when a component is invalid', function (): void {
    $component = new TextInput('name', 'Name', new Property(TextInput::MaxLength, 'many'));

    GcegoTest::component($component)->assertInvalid(ErrorCode::InvalidType);

    $message = failure(fn () => GcegoTest::component($component)->assertValid());

    expect($message)->toContain('GCEGO COMPONENT ASSERTION FAILED')
        ->toContain('Expected the component ['.TextInput::class.'] to be valid.')
        ->toContain('Invalid property "max_length"');
});

it('fails with expected and actual values', function (): void {
    $message = failure(fn () => GcegoTest::component(new TextInput('name', 'Name'))->assertLabel('Title'));

    expect($message)->toContain('Assertion: assertLabel(Title)')
        ->toContain("Expected:\n  label = Title")
        ->toContain("Actual:\n  label = Name");
});

it('checks properties, defaults and accepted values without changing the component', function (): void {
    $component = new TextInput('name', 'Name', new Property(TextInput::Required, true));
    $before = $component->normalize();

    GcegoTest::component($component)
        ->assertHasProperty(TextInput::Required)
        ->assertPropertyEquals(TextInput::Required, true)
        ->assertAcceptsValue(TextInput::MaxLength, 20)
        ->assertRejectsValue(TextInput::MaxLength, 'twenty')
        ->assertNormalized(['properties' => ['name' => 'name']]);

    expect($component->normalize())->toBe($before);
    failure(fn () => GcegoTest::component($component)->assertPropertyEquals(TextInput::Required, false));
});
