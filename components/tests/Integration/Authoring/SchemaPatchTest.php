<?php

declare(strict_types=1);

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\PatchException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Forms\TextInput;
use Gcego\Components\Layout\Schema;
use Gcego\Components\Layout\Section;
use Gcego\Components\Patching\SchemaPatch;

function profileSchema(): Schema
{
    return Schema::make()->components([
        Section::make('account')->schema([
            TextInput::make('name'),
            TextInput::make('email'),
            TextInput::make('legacy_code'),
        ]),
        Section::make('billing')->schema([
            TextInput::make('email'),
        ]),
    ])->build();
}

/**
 * @return list<string>
 */
function childNames(ComponentInterface $component): array
{
    return array_map(static fn (ComponentInterface $child): string => (string) $child->get('name'), $component->children()->all());
}

it('inserts, removes and appends components without changing the original', function (): void {
    $original = profileSchema();

    $patched = Components::patch($original)
        ->insertBefore('account.name', TextInput::make('title'))
        ->insertAfter('account.email', TextInput::make('phone'))
        ->remove('legacy_code')
        ->append('billing', TextInput::make('vat'))
        ->prepend('billing', TextInput::make('company'))
        ->apply();

    expect(childNames($patched->children()->all()[0]))->toBe(['title', 'name', 'email', 'phone'])
        ->and(childNames($patched->children()->all()[1]))->toBe(['company', 'email', 'vat'])
        ->and(childNames($original->children()->all()[0]))->toBe(['name', 'email', 'legacy_code'])
        ->and($patched->validate()->isValid())->toBeTrue();
});

it('modifies every match of a name, or only the one inside a named container', function (): void {
    $everywhere = Components::patch(profileSchema())->modify('email', fn (ComponentBuilder $email): ComponentBuilder => $email->required())->apply();
    $scoped = Components::patch(profileSchema())->modify('billing.email', fn (ComponentBuilder $email): ComponentBuilder => $email->required())->apply();

    $required = static fn (ComponentInterface $schema): array => [
        $schema->children()->all()[0]->children()->all()[1]->get('required'),
        $schema->children()->all()[1]->children()->all()[0]->get('required'),
    ];

    expect($required($everywhere))->toBe([true, true])
        ->and($required($scoped))->toBe([null, true]);
});

it('keeps children and configuration when modifying a container', function (): void {
    $patched = Components::patch(profileSchema())->modify('account', fn (ComponentBuilder $section): ComponentBuilder => $section->columns(2))->apply();
    $account = $patched->children()->all()[0];

    expect($account->get('columns'))->toBe(2)
        ->and(childNames($account))->toBe(['name', 'email', 'legacy_code']);
});

it('fails with a suggestion when a selector matches nothing', function (): void {
    expect(fn () => Components::patch(profileSchema())->remove('acount.emial')->apply())
        ->toThrow(PatchException::class, 'Did you mean "account.email"?');
});

it('applies registered extensions in order to the schema published under a key', function (): void {
    Components::extend('profile', fn (SchemaPatch $patch): SchemaPatch => $patch->insertAfter('account.email', TextInput::make('phone')));
    Components::extend('profile', fn (SchemaPatch $patch): SchemaPatch => $patch->modify('phone', fn (ComponentBuilder $phone): ComponentBuilder => $phone->required()));

    $account = Components::extended('profile', Schema::make()->components([Section::make('account')->schema([TextInput::make('email')])]))
        ->children()->all()[0];

    expect(childNames($account))->toBe(['email', 'phone'])
        ->and($account->children()->all()[1]->get('required'))->toBeTrue()
        ->and(childNames(Components::extended('other', profileSchema())->children()->all()[0]))->toBe(['name', 'email', 'legacy_code']);
});

it('rejects extensions that do not return the patch', function (): void {
    Components::extend('profile', fn (SchemaPatch $patch) => null);

    expect(fn () => Components::extended('profile', profileSchema()))->toThrow(InvalidBuilderException::class, 'must return the patch');
});

it('refuses to target the schema root', function (): void {
    $schema = Schema::make()->components([TextInput::make('email')])->build();

    expect(fn () => Components::patch($schema)->remove('#0')->apply())->toThrow(PatchException::class, 'No component matches "#0"');
});
