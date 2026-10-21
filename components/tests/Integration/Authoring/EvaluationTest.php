<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Evaluation\Get;
use Gcego\Components\Evaluation\Set;
use Gcego\Components\Evaluation\State;
use Gcego\Components\Exceptions\EvaluationException;
use Gcego\Components\Forms\TextInput;
use Gcego\Components\Layout\Section;
use Gcego\Components\Normalization\RuntimePlaceholder;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\FakeModel;
use Illuminate\Foundation\Auth\User;

it('stores closures as computed values that validate and normalize without being called', function (): void {
    $calls = 0;
    $input = TextInput::make('email')->required(function () use (&$calls): bool {
        $calls++;

        return true;
    })->build();

    expect($input->isComputed('required'))->toBeTrue()
        ->and($input->validate()->isValid())->toBeTrue()
        ->and($input->normalize()['properties']['required'])->toEqual(new RuntimePlaceholder(Computed::class))
        ->and($calls)->toBe(0)
        ->and($input->evaluateProperty('required'))->toBeTrue()
        ->and($calls)->toBe(1);
});

it('injects closure parameters by name, by type and as the component itself', function (): void {
    $record = new FakeModel;
    $input = TextInput::make('email')
        ->placeholder(fn (string $operation, FakeModel $model, Component $component): string => $operation.':'.$model::class.':'.$component->name())
        ->build();

    expect($input->evaluateProperty('placeholder', ['operation' => 'edit', 'record' => $record]))
        ->toBe('edit:'.FakeModel::class.':email');
});

it('injects get, set and the component state relative to the container path', function (): void {
    $state = new State(['data' => ['type' => 'business', 'address' => ['city' => 'Buea', 'country' => 'CM']]]);
    $city = TextInput::make('city')
        ->placeholder(function (Get $get, Set $set, ?string $state): string {
            $set('../summary', $state.', '.$get('country'));

            return $get('../type', isAbsolute: false).' / '.$get('data.type', isAbsolute: true);
        })
        ->build();

    $placeholder = $city->evaluateProperty('placeholder', EvaluationContext::make()->withState($state, 'data.address'));

    expect($placeholder)->toBe('business / business')
        ->and($state->get('data.summary'))->toBe('Buea, CM');
});

it('falls back to the property default when a closure returns null', function (): void {
    $component = ExampleComponent::define('name')->perPage(fn (): ?int => null)->build();

    expect($component->evaluateProperty('perPage'))->toBe(10);
});

it('checks closure results against the property definition', function (mixed $result, string $expected): void {
    $component = ExampleComponent::define('name')->perPage(fn (): mixed => $result)->build();

    expect(fn () => $component->evaluateProperty('perPage'))->toThrow(EvaluationException::class, 'Expected: '.$expected);
})->with([
    'wrong type' => ['ten', 'int'],
    'failed rule' => [0, 'integer greater than zero'],
]);

it('names the available parameters and suggests the closest one when injection fails', function (): void {
    $input = TextInput::make('email')->placeholder(fn (string $recrod): string => $recrod)->build();

    expect(fn () => $input->evaluateProperty('placeholder', ['record' => 'value']))
        ->toThrow(EvaluationException::class, 'Did you mean $record?');
});

it('explains that get and set need state when none is supplied', function (): void {
    $input = TextInput::make('email')->placeholder(fn (Get $get): string => (string) $get('name'))->build();

    expect(fn () => $input->evaluateProperty('placeholder'))
        ->toThrow(EvaluationException::class, 'Supply state with EvaluationContext::withState()');
});

it('resolves visibility from hidden, visible, hiddenOn and visibleOn', function (string $method, array $arguments, ?string $operation, bool $hidden): void {
    $section = Section::make('billing')->{$method}(...$arguments)->build();

    expect($section->isHiddenIn(['operation' => $operation]))->toBe($hidden)
        ->and($section->isVisibleIn(['operation' => $operation]))->toBe(! $hidden);
})->with([
    'hidden' => ['hidden', [], null, true],
    'visible closure false' => ['visible', [fn (?string $operation): bool => $operation === 'edit'], 'create', true],
    'visible closure true' => ['visible', [fn (?string $operation): bool => $operation === 'edit'], 'edit', false],
    'hidden on matching operation' => ['hiddenOn', ['create', 'view'], 'view', true],
    'hidden on other operation' => ['hiddenOn', ['create'], 'edit', false],
    'visible on matching operation' => ['visibleOn', [['edit']], 'edit', false],
    'visible on other operation' => ['visibleOn', ['edit'], 'create', true],
    'visible on without operation' => ['visibleOn', ['edit'], null, true],
]);

it('derives a readable label from the name unless one is set', function (): void {
    expect(TextInput::make('billing.first_name')->build()->displayLabel())->toBe('First name')
        ->and(TextInput::make('emailAddress')->build()->displayLabel())->toBe('Email address')
        ->and(TextInput::make('email')->label(fn (string $operation): string => ucfirst($operation).' email')->build()->displayLabel(['operation' => 'edit']))->toBe('Edit email');
});

it('evaluates every effective property at once', function (): void {
    $properties = ExampleComponent::define('name')->label(fn (): string => 'Computed')->build()->evaluatedProperties();

    expect($properties['label'])->toBe('Computed')
        ->and($properties['perPage'])->toBe(10);
});

it('never builds records from the container, only injects them from the context', function (): void {
    $input = TextInput::make('email')->placeholder(fn (User $record): string => (string) $record->getKey())->build();
    $user = (new User)->forceFill(['id' => 7]);

    expect($input->evaluateProperty('placeholder', ['record' => $user]))->toBe('7')
        ->and(fn () => $input->evaluateProperty('placeholder'))->toThrow(EvaluationException::class, 'Cannot inject parameter $record');
});
