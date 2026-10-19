<?php

declare(strict_types=1);

use Gcego\Chroma\Classes\ClassMerger;

function merged(string $base, string $override): string
{
    return implode(' ', (new ClassMerger)->merge(explode(' ', $base), explode(' ', $override)));
}

it('replaces conflicting utilities in place and keeps unrelated ones', function (): void {
    expect(merged('rounded-lg p-4 bg-white', 'rounded-2xl p-6'))->toBe('rounded-2xl p-6 bg-white');
});

it('resolves documented utility groups', function (string $base, string $override, string $expected): void {
    expect(merged($base, $override))->toBe($expected);
})->with([
    'padding' => ['p-4', 'p-6', 'p-6'],
    'shorthand overrides axis' => ['px-2 py-1', 'p-4', 'p-4'],
    'axis does not override shorthand' => ['p-4', 'px-6', 'p-4 px-6'],
    'axis overrides sides' => ['pl-2 pr-3 pt-1', 'px-4', 'px-4 pt-1'],
    'margin with negatives' => ['-mt-2', 'mt-4', 'mt-4'],
    'radius' => ['rounded', 'rounded-xl', 'rounded-xl'],
    'radius shorthand over corners' => ['rounded-tl-lg rounded-br-sm', 'rounded-md', 'rounded-md'],
    'radius side does not touch others' => ['rounded-lg', 'rounded-t-none', 'rounded-lg rounded-t-none'],
    'text size vs color' => ['text-sm text-slate-600', 'text-lg', 'text-lg text-slate-600'],
    'text color vs size' => ['text-sm text-slate-600', 'text-red-700', 'text-sm text-red-700'],
    'text size overrides leading' => ['leading-7 text-sm', 'text-base', 'text-base'],
    'text align' => ['text-left', 'text-center', 'text-center'],
    'background color vs image' => ['bg-white bg-linear-to-r', 'bg-slate-50', 'bg-slate-50 bg-linear-to-r'],
    'gcego token colors' => ['bg-gcego-surface text-gcego-text', 'bg-gcego-surface-muted', 'bg-gcego-surface-muted text-gcego-text'],
    'border width vs color' => ['border border-slate-200', 'border-2', 'border-2 border-slate-200'],
    'border color' => ['border border-slate-200', 'border-red-500', 'border border-red-500'],
    'border side width' => ['border', 'border-t-2', 'border border-t-2'],
    'border style' => ['border-solid', 'border-dashed', 'border-dashed'],
    'shadow size' => ['shadow-sm', 'shadow-lg', 'shadow-lg'],
    'shadow token' => ['shadow-gcego-card', 'shadow-none', 'shadow-none'],
    'shadow color is separate' => ['shadow-sm', 'shadow-black/5', 'shadow-sm shadow-black/5'],
    'display' => ['flex', 'grid', 'grid'],
    'hidden is display' => ['block', 'hidden', 'hidden'],
    'position' => ['relative', 'absolute', 'absolute'],
    'font weight vs family' => ['font-sans font-medium', 'font-bold', 'font-sans font-bold'],
    'gap shorthand' => ['gap-x-2 gap-y-4', 'gap-6', 'gap-6'],
    'gap axes are independent' => ['gap-x-4', 'gap-y-2', 'gap-x-4 gap-y-2'],
    'gap axis overrides its axis' => ['gap-x-4 gap-y-2', 'gap-x-8', 'gap-x-8 gap-y-2'],
    'overflow axes are independent' => ['overflow-y-auto', 'overflow-x-hidden', 'overflow-y-auto overflow-x-hidden'],
    'overflow shorthand' => ['overflow-x-auto overflow-y-hidden', 'overflow-auto', 'overflow-auto'],
    'inset axes are independent' => ['inset-x-0', 'inset-y-4', 'inset-x-0 inset-y-4'],
    'inset shorthand' => ['inset-x-0 top-2', 'inset-0', 'inset-0'],
    'grid columns' => ['grid-cols-1', 'grid-cols-3', 'grid-cols-3'],
    'width' => ['w-full', 'w-1/2', 'w-1/2'],
    'max width independent of width' => ['w-full', 'max-w-sm', 'w-full max-w-sm'],
    'size overrides width' => ['w-4 h-4', 'size-6', 'size-6'],
    'ring width vs color' => ['ring-1 ring-slate-200', 'ring-2', 'ring-2 ring-slate-200'],
    'outline' => ['outline-2 outline-gcego-ring', 'outline-4', 'outline-4 outline-gcego-ring'],
    'outline offset is separate' => ['outline-2 outline-offset-4', 'outline-gcego-ring', 'outline-2 outline-offset-4 outline-gcego-ring'],
    'outline offset' => ['outline-offset-2', 'outline-offset-4', 'outline-offset-4'],
    'ring offset is separate' => ['ring-2 ring-offset-2 ring-offset-white', 'ring-gcego-ring', 'ring-2 ring-offset-2 ring-offset-white ring-gcego-ring'],
    'ring offset width' => ['ring-offset-2', 'ring-offset-4', 'ring-offset-4'],
    'flex direction' => ['flex-row', 'flex-col', 'flex-col'],
    'flex shorthand' => ['grow shrink-0', 'flex-1', 'flex-1'],
    'justify' => ['justify-start', 'justify-between', 'justify-between'],
    'items' => ['items-start', 'items-center', 'items-center'],
    'opacity' => ['opacity-50', 'opacity-100', 'opacity-100'],
    'z-index' => ['z-10', 'z-50', 'z-50'],
    'arbitrary value' => ['p-[3px]', 'p-[5px]', 'p-[5px]'],
    'arbitrary property' => ['[mask-type:luminance]', '[mask-type:alpha]', '[mask-type:alpha]'],
    'important is separate' => ['p-4', 'p-6!', 'p-4 p-6!'],
    'duplicate' => ['p-4 flex', 'flex', 'p-4 flex'],
    'transition' => ['transition', 'transition-colors', 'transition-colors'],
]);

it('scopes conflicts by variant stack', function (string $base, string $override, string $expected): void {
    expect(merged($base, $override))->toBe($expected);
})->with([
    'hover separate from base' => ['bg-white hover:bg-slate-50', 'bg-slate-100', 'bg-slate-100 hover:bg-slate-50'],
    'hover replaces hover' => ['hover:bg-slate-50', 'hover:bg-slate-100', 'hover:bg-slate-100'],
    'responsive separate' => ['p-4 md:p-6', 'p-2', 'p-2 md:p-6'],
    'responsive replaces responsive' => ['md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-3'],
    'dark separate' => ['text-slate-900 dark:text-white', 'text-black', 'text-black dark:text-white'],
    'variant order irrelevant' => ['md:hover:p-4', 'hover:md:p-6', 'hover:md:p-6'],
    'focus-visible' => ['focus-visible:outline-2', 'focus-visible:outline-4', 'focus-visible:outline-4'],
    'arbitrary variant' => ['[&>*]:p-2', '[&>*]:p-4', '[&>*]:p-4'],
]);

it('never removes utilities it does not know', function (): void {
    expect(merged('gcego-custom-utility mask-radial-at-center', 'another-custom bg-white'))->toBe('gcego-custom-utility mask-radial-at-center another-custom bg-white');
});

it('reports conflicts', function (): void {
    $merger = new ClassMerger;

    expect($merger->conflicts('p-4', 'px-2'))->toBeTrue()
        ->and($merger->conflicts('px-2', 'p-4'))->toBeTrue()
        ->and($merger->conflicts('p-4', 'm-4'))->toBeFalse()
        ->and($merger->conflicts('hover:p-4', 'p-4'))->toBeFalse()
        ->and($merger->conflicts('unknown-a', 'unknown-b'))->toBeFalse();
});
