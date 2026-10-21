<?php

declare(strict_types=1);

use Gcego\Components\Evaluation\Get;
use Gcego\Components\Evaluation\Set;
use Gcego\Components\Facades\Components;
use Gcego\Components\Forms\Select;
use Gcego\Components\Forms\TextInput;
use Gcego\Components\Layout\Group;
use Gcego\Components\Layout\Schema;
use Gcego\Components\Layout\Section;

function customerSchema(): Schema
{
    return Schema::make()->components([
        Section::make('account')->schema([
            Select::make('type')->options(['person' => 'Person', 'business' => 'Business'])->default('person'),
            TextInput::make('title')
                ->afterStateUpdated(fn (Set $set, ?string $state, ?string $old) => $set('slug', strtolower((string) $state).'|'.$old)),
            TextInput::make('slug'),
            TextInput::make('company')->visible(fn (Get $get): bool => $get('type') === 'business'),
            TextInput::make('notes')->dehydrated(false),
        ]),
        Group::make('address')->statePath('address')->schema([
            TextInput::make('city')->default(fn (string $operation): string => $operation === 'create' ? 'Buea' : ''),
            TextInput::make('country')->placeholder(fn (Get $get): string => 'Near '.$get('city')),
        ]),
    ])->build();
}

it('fills missing defaults without overwriting supplied state, nesting by container state path', function (): void {
    $live = Components::live(customerSchema(), ['title' => 'Hello'], ['operation' => 'create']);

    expect($live->state()->all())->toMatchArray([
        'type' => 'person',
        'title' => 'Hello',
        'slug' => null,
        'address' => ['city' => 'Buea', 'country' => null],
    ]);
});

it('discovers which fields are live from the closures that read them', function (): void {
    $live = Components::live(customerSchema(), [], ['operation' => 'create']);

    expect($live->isLive('type'))->toBeTrue()
        ->and($live->isLive('address.city'))->toBeTrue()
        ->and($live->isLive('title'))->toBeFalse()
        ->and($live->livePaths())->toBe(['type', 'address.city'])
        ->and($live->dependentsOf('type'))->toBe(['#0.account.company']);
});

it('reports exactly the components an update changes', function (): void {
    $live = Components::live(customerSchema(), [], ['operation' => 'create']);

    $changes = $live->set('type', 'business');

    expect($live->field('company')?->isVisible())->toBeTrue()
        ->and($changes->statePaths)->toBe(['type'])
        ->and($changes->components)->toBe(['#0.account.type', '#0.account.company']);
});

it('runs afterStateUpdated with the new and old state and reports the values it set', function (): void {
    $live = Components::live(customerSchema(), ['title' => 'Old'], ['operation' => 'create']);

    $changes = $live->set('title', 'New');

    expect($live->state()->get('slug'))->toBe('new|Old')
        ->and($changes->statePaths)->toBe(['title', 'slug'])
        ->and($changes->components)->toBe(['#0.account.title', '#0.account.slug']);
});

it('dehydrates only visible fields that are not excluded', function (): void {
    $live = Components::live(customerSchema(), ['company' => 'Acme', 'notes' => 'private'], ['operation' => 'create']);

    expect($live->dehydrate())->not->toHaveKeys(['company', 'notes'])
        ->and($live->dehydrate()['address'])->toBe(['city' => 'Buea', 'country' => null]);

    $live->set('type', 'business');

    expect($live->dehydrate()['company'])->toBe('Acme');
});

it('does not resolve or dehydrate the children of hidden containers', function (): void {
    $schema = Schema::make()->components([
        TextInput::make('mode'),
        Section::make('advanced')->visible(fn (Get $get): bool => $get('mode') === 'advanced')->schema([
            TextInput::make('secret')->placeholder(fn (): string => throw new LogicException('must not run')),
        ]),
    ])->build();

    $live = Components::live($schema, ['secret' => 'x']);

    expect($live->at('#0.advanced')?->hidden)->toBeTrue()
        ->and($live->at('#0.advanced.secret'))->toBeNull()
        ->and($live->dehydrate())->toBe(['mode' => null]);
});

it('exports a renderer-neutral snapshot with labels, live flags and dependencies', function (): void {
    $snapshot = Components::live(customerSchema(), [], ['operation' => 'create'])->toArray();
    $account = $snapshot['components']['children'][0];

    expect($account['children'][0])->toMatchArray(['name' => 'type', 'label' => 'Type', 'statePath' => 'type', 'live' => true, 'hidden' => false])
        ->and($account['children'][3])->toMatchArray(['name' => 'company', 'hidden' => true, 'dependsOn' => ['type']])
        ->and($snapshot['components']['children'][1]['children'][1]['properties']['placeholder'])->toBe('Near Buea')
        ->and(json_encode($snapshot, JSON_THROW_ON_ERROR))->toBeString();
});

it('rejects non-closure afterStateUpdated values', function (): void {
    expect(TextInput::make('x')->afterStateUpdated('not callable')->validate()->isValid())->toBeFalse();
});
