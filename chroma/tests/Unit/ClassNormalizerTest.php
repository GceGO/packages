<?php

declare(strict_types=1);

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Classes\ClassNormalizer;
use Gcego\Chroma\Exceptions\InvalidClassException;

it('normalizes lists and strings to the same tokens', function (): void {
    $fromList = (new ClassNormalizer)->normalize(['rounded-xl', 'border', 'bg-white', 'shadow-sm']);
    $fromString = (new ClassNormalizer)->normalize('rounded-xl border bg-white shadow-sm');

    expect($fromList)->toBe(['rounded-xl', 'border', 'bg-white', 'shadow-sm'])
        ->and($fromString)->toBe($fromList);
});

it('removes empty entries, extra whitespace and exact duplicates, keeping first order', function (): void {
    expect((new ClassNormalizer)->normalize(["  p-4\n\t", '', 'p-4', ' flex   gap-2 ', 'flex']))->toBe(['p-4', 'flex', 'gap-2']);
});

it('keeps variants and arbitrary values intact', function (string $class): void {
    expect((new ClassNormalizer)->normalize([$class]))->toBe([$class]);
})->with([
    'hover:bg-slate-50', 'dark:md:hover:text-white', 'focus-visible:outline-2', 'aria-invalid:border-red-600',
    'data-[state=open]:rotate-180', 'grid-cols-[1fr_auto]', '[mask-type:luminance]', 'w-[calc(100%-2rem)]',
    'bg-[url(/img/x.png)]', 'content-[\'hi\']', 'p-4!', '!p-4', '-mt-2', '[&>*]:p-4', '*:rounded', '@container', '@md:grid-cols-2',
    'supports-[display:grid]:grid', 'group-hover/item:underline', '[&::-webkit-details-marker]:hidden', 'md:[&::placeholder]:text-sm',
]);

it('does not split inside brackets or parentheses', function (): void {
    expect((new ClassNormalizer)->split('grid-cols-[1fr_auto] w-[calc(100%-1rem)] p-4'))->toBe(['grid-cols-[1fr_auto]', 'w-[calc(100%-1rem)]', 'p-4']);
});

it('rejects invalid input', function (mixed $input, string $message): void {
    expect(fn () => (new ClassNormalizer)->normalize($input))->toThrow(InvalidClassException::class, $message);
})->with([
    'non-string entry' => [['p-4', 42], 'Tailwind classes must be strings or lists of strings, int given.'],
    'quote' => [['bg-red-500"onclick="x'], 'double quotes'],
    'angle bracket' => [['<script>'], 'double quotes, "<"'],
    'brace' => [['p-4{color:red}'], 'braces'],
    'space inside brackets' => [['w-[1rem 2rem]'], 'whitespace'],
    'unbalanced' => [['grid-cols-[1fr'], 'unbalanced'],
    'empty variant' => [['hover::bg-red'], 'empty variant'],
    'empty variant after brackets' => [['[&>*]::p-4'], 'empty variant'],
    'too long' => [[str_repeat('a', 201)], 'longer than 200'],
]);

it('wraps normalized tokens in an immutable ClassList', function (): void {
    $list = ClassList::from(['p-4', 'flex']);

    expect($list->all())->toBe(['p-4', 'flex'])
        ->and((string) $list)->toBe('p-4 flex')
        ->and($list->contains('flex'))->toBeTrue()
        ->and($list->count())->toBe(2)
        ->and(iterator_to_array($list))->toBe(['p-4', 'flex'])
        ->and(ClassList::from($list))->toBe($list)
        ->and(ClassList::empty()->isEmpty())->toBeTrue()
        ->and($list->without(ClassList::from('flex'))->all())->toBe(['p-4'])
        ->and($list->all())->toBe(['p-4', 'flex']);
});
