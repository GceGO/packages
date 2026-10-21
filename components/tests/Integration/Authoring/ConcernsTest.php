<?php

declare(strict_types=1);

use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Forms\TextInput;
use Gcego\Components\Layout\Grid;
use Gcego\Components\Layout\Section;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Validation\ErrorCode;

it('accepts one column count or one per breakpoint', function (int|array $columns): void {
    expect(Grid::make('layout')->columns($columns)->validate()->isValid())->toBeTrue();
})->with([
    'single' => [3],
    'responsive' => [['default' => 1, 'md' => 2, '2xl' => 4]],
]);

it('rejects invalid column counts and unknown breakpoints', function (mixed $columns): void {
    $errors = Section::make('profile')->columns($columns)->validate()->errors();

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->code)->toBe(ErrorCode::InvalidValue)
        ->and($errors[0]->expected)->toContain('default, sm, md, lg, xl, 2xl');
})->with([
    'zero' => [0],
    'empty map' => [[]],
    'unknown breakpoint' => [['tablet' => 2]],
    'non-positive per breakpoint' => [['md' => -1]],
]);

it('spans columns by count, by breakpoint or fully', function (): void {
    $span = TextInput::make('bio')->columnSpan(['default' => 'full', 'lg' => 2])->columnStart(2)->build();

    expect(TextInput::make('notes')->columnSpanFull()->build()->get('columnSpan'))->toBe('full')
        ->and($span->validate()->isValid())->toBeTrue()
        ->and(TextInput::make('x')->columnSpan('half')->validate()->isValid())->toBeFalse();
});

it('replaces extra attributes unless asked to merge them', function (): void {
    $builder = TextInput::make('email')->extraAttributes(['data-test' => 'email', 'autofocus' => true]);

    expect($builder->extraAttributes(['class' => 'wide'])->build()->get('extraAttributes'))->toBe(['class' => 'wide'])
        ->and($builder->extraAttributes(['class' => 'wide'], merge: true)->build()->get('extraAttributes'))
        ->toBe(['data-test' => 'email', 'autofocus' => true, 'class' => 'wide'])
        ->and(TextInput::make('x')->extraAttributes(['bad name' => 'x'])->validate()->isValid())->toBeFalse();
});

it('reports a typo in a concern method with a suggestion', function (): void {
    $errors = TextInput::make('email')->set('colunmSpan', 2)->validate()->errors();

    expect($errors[0]->hint)->toBe('Did you mean "columnSpan"?');
});

it('offers concern methods only to components that use the concern', function (): void {
    expect(fn () => ExampleComponent::define('name')->columnSpanFull())->toThrow(InvalidBuilderException::class, 'has no "columnSpan" setting')
        ->and(fn () => TextInput::make('email')->columns(2))->toThrow(InvalidBuilderException::class, 'has no "columns" setting');
});

it('only accepts concern classes in withConcerns()', function (): void {
    expect(fn () => (new ComponentDefinition('fixtures.concerns'))->withConcerns(Section::class))
        ->toThrow(InvalidConfigurationException::class, 'is not a Gcego\Components\Contracts\Concern');
});
