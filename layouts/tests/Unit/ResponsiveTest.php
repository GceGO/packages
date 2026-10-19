<?php

declare(strict_types=1);

use Gcego\Layouts\Configuration\Align;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\Exceptions\InvalidLayoutException;
use Gcego\Layouts\LayoutsConfig;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Responsive\Breakpoint;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Responsive\ResponsiveValue;
use Gcego\Layouts\Responsive\Visibility;

it('parses breakpoints in mobile-first order', function (): void {
    expect(Breakpoint::parse('2xl'))->toBe(Breakpoint::Xxl)
        ->and(Breakpoint::Md->order())->toBeGreaterThan(Breakpoint::Sm->order())
        ->and(Breakpoint::names())->toBe(['default', 'sm', 'md', 'lg', 'xl', '2xl'])
        ->and(fn () => Breakpoint::parse('tablet'))->toThrow(InvalidLayoutException::class, '"tablet" is not a breakpoint');
});

it('cascades responsive values upwards and emits classes only where they change', function (): void {
    $value = ResponsiveValue::of(['xl' => 4, 'md' => 2], static fn (): bool => true, 'Test', 'anything');

    expect($value->values)->toBe(['md' => 2, 'xl' => 4])
        ->and($value->at(Breakpoint::Sm))->toBeNull()
        ->and($value->at(Breakpoint::Lg))->toBe(2)
        ->and($value->at(Breakpoint::Xxl))->toBe(4)
        ->and($value->classes(LayoutPresets::GridColumns))->toBe('md:grid-cols-2 xl:grid-cols-4')
        ->and(ResponsiveValue::of(['default' => 2, 'lg' => 2], static fn (): bool => true, 'Test', '')->classes(LayoutPresets::GridColumns))->toBe('grid-cols-2')
        ->and($value->normalize())->toBe(['md' => 2, 'xl' => 4])
        ->and(ResponsiveValue::of(3, static fn (): bool => true, 'Test', '')->normalize())->toBe(3);
});

it('rejects unknown breakpoints, empty maps and invalid values', function (mixed $value, string $message): void {
    expect(fn () => ResponsiveValue::of($value, static fn (mixed $count): bool => is_int($count) && $count < 5, 'Columns', 'a small number'))->toThrow(InvalidLayoutException::class, $message);
})->with([
    'unknown breakpoint' => [['phone' => 1], '"phone" is not a breakpoint'],
    'list instead of map' => [[1, 2], '"0" is not a breakpoint'],
    'empty' => [[], 'at least one breakpoint'],
    'out of range' => [['md' => 9], 'the value at "md" must be a small number'],
    'wrong type' => [['md' => 1.5], 'the value at "md" must be a small number'],
]);

it('expands a column count into professional responsive defaults', function (int|array $columns, string $classes, int $maximum): void {
    $node = new Columns($columns);

    expect($node->classes())->toBe($classes)->and($node->maximum())->toBe($maximum);
})->with([
    'one' => [1, 'grid-cols-1', 1],
    'two' => [2, 'grid-cols-1 sm:grid-cols-2', 2],
    'three' => [3, 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3', 3],
    'four' => [4, 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4', 4],
    'six' => [6, 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6', 6],
    'map' => [['md' => 2, 'xl' => 4], 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4', 4],
    'map with default' => [['default' => 2, 'lg' => 3], 'grid-cols-2 lg:grid-cols-3', 3],
]);

it('caps automatic grids at four columns', function (): void {
    expect(Columns::auto(0)->classes())->toBe('grid-cols-1')
        ->and(Columns::auto(9)->classes())->toBe('grid-cols-1 sm:grid-cols-2 lg:grid-cols-4');
});

it('rejects column counts outside 1 to 6', function (int|array $columns): void {
    expect(fn () => new Columns($columns))->toThrow(InvalidLayoutException::class, 'a whole number from 1 to 6');
})->with([[0], [7], [['md' => 12]]]);

it('limits spans to the columns at each breakpoint, so they never overflow', function (int|string|array $span, int|array $columns, string $classes): void {
    expect((new ColumnSpan($span))->classes(new Columns($columns)))->toBe($classes);
})->with([
    'two of three' => [2, 3, 'sm:col-span-2'],
    'three of three' => [3, 3, 'sm:col-span-2 lg:col-span-3'],
    'full' => ['full', 3, 'col-span-full'],
    'auto' => ['auto', 3, 'col-auto'],
    'full on phones, one from lg' => [['default' => 'full', 'lg' => 1], 3, 'col-span-full lg:col-span-1'],
    'responsive grid' => [3, ['md' => 2, 'xl' => 4], 'md:col-span-2 xl:col-span-3'],
    'one' => [1, 4, ''],
]);

it('rejects invalid spans', function (mixed $span): void {
    expect(fn () => new ColumnSpan($span))->toThrow(InvalidLayoutException::class, '1 to 6, "full" or "auto"');
})->with([[0], [7], ['half'], [['lg' => 'wide']]]);

it('builds gaps from the bounded spacing scale', function (): void {
    expect((new Gap(4))->classes(6))->toBe('gap-4')
        ->and((new Gap(['default' => 4, 'lg' => 8]))->classes(6))->toBe('gap-4 lg:gap-8')
        ->and((new Gap(row: 8, column: 2))->classes(6))->toBe('gap-y-8 gap-x-2')
        ->and((new Gap(row: 8))->classes(6))->toBe('gap-6 gap-y-8')
        ->and((new Gap(3))->normalize())->toBe(['gap' => 3, 'row' => null, 'column' => null])
        ->and(fn () => new Gap(5))->toThrow(InvalidLayoutException::class, 'a spacing step')
        ->and(fn () => new Gap(row: 7))->toThrow(InvalidLayoutException::class, 'row must be a spacing step')
        ->and(fn () => new Gap)->toThrow(InvalidLayoutException::class, 'set a gap');
});

it('aligns with typed, responsive values', function (): void {
    expect((new Align(items: 'center', justify: 'between'))->classes())->toBe('items-center justify-between')
        ->and((new Align(items: ['default' => 'stretch', 'md' => 'center']))->classes())->toBe('items-stretch md:items-center')
        ->and(fn () => new Align(items: 'middle'))->toThrow(InvalidLayoutException::class, 'one of start, center, end, stretch, baseline')
        ->and(fn () => new Align)->toThrow(InvalidLayoutException::class, 'set items, justify or both');
});

it('hides children at some widths, never everywhere', function (): void {
    expect((new Visibility(hiddenBelow: 'md'))->classes())->toBe('max-md:hidden')
        ->and((new Visibility(hiddenFrom: 'lg'))->classes())->toBe('lg:hidden')
        ->and((new Visibility(hiddenBelow: 'sm', hiddenFrom: 'xl'))->classes())->toBe('max-sm:hidden xl:hidden')
        ->and(fn () => new Visibility)->toThrow(InvalidLayoutException::class, 'set hiddenBelow, hiddenFrom or both')
        ->and(fn () => new Visibility(hiddenBelow: 'default'))->toThrow(InvalidLayoutException::class, 'hide the child everywhere')
        ->and(fn () => new Visibility(hiddenBelow: 'lg', hiddenFrom: 'md'))->toThrow(InvalidLayoutException::class, 'never visible');
});

it('validates its configuration', function (array $config, string $message): void {
    expect(fn () => LayoutsConfig::fromArray($config))->toThrow(InvalidLayoutException::class, $message);
})->with([
    'width' => [['container' => ['default_max_width' => 'huge']], 'default_max_width'],
    'gap' => [['grid' => ['default_gap' => 7]], 'default_gap'],
    'gap type' => [['grid' => ['default_gap' => '6']], 'default_gap'],
    'limit' => [['diagnostics' => ['max_nodes' => 0]], 'max_nodes'],
]);

it('uses relative units only, so layouts follow browser zoom and font size', function (): void {
    $classes = implode(' ', array_map(static fn (mixed $value): string => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value, (new ReflectionClass(LayoutPresets::class))->getConstants()));

    expect(preg_match('/\d+px/', $classes))->toBe(0)
        ->and(LayoutsConfig::fromArray([])->defaultGap)->toBe(6);
});
