<?php

declare(strict_types=1);

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Exceptions\InvalidClassException;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Slot;
use Gcego\Chroma\SlotMode;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;

it('accepts one class per line or class strings', function (): void {
    $lines = new Slot(Tailwind::Wrapper, [
        'rounded-2xl',
        'border',
        'bg-white',
    ]);
    $string = new Slot(Tailwind::Wrapper, 'rounded-2xl border bg-white');

    expect($lines->classes->all())->toBe(['rounded-2xl', 'border', 'bg-white'])
        ->and($string->classes->all())->toBe($lines->classes->all())
        ->and($lines->mode)->toBe(SlotMode::Merge)
        ->and($lines->toArray())->toBe(['name' => 'root', 'mode' => 'merge', 'classes' => ['rounded-2xl', 'border', 'bg-white']]);
});

it('applies merge, replace and remove modes', function (): void {
    $base = ClassList::from('rounded-lg p-4 bg-white shadow-sm');

    expect((new Slot('root', ['rounded-2xl', 'p-6']))->applyTo($base)->toString())->toBe('rounded-2xl p-6 bg-white shadow-sm')
        ->and(Slot::replace('root', ['p-2'])->applyTo($base)->toString())->toBe('p-2')
        ->and(Slot::remove('root', ['shadow-sm', 'not-present'])->applyTo($base)->toString())->toBe('rounded-lg p-4 bg-white')
        ->and(new Slot('root', 'p-2', SlotMode::Replace))->toEqual(Slot::replace('root', 'p-2'));
});

it('validates slot names and classes', function (): void {
    expect(fn () => new Slot('Wrapper', 'p-4'))->toThrow(InvalidSlotException::class, '"Wrapper" is not a valid slot name')
        ->and(fn () => new Slot('', 'p-4'))->toThrow(InvalidSlotException::class)
        ->and(fn () => new Slot('root', ['p-4', 1]))->toThrow(InvalidClassException::class); // @phpstan-ignore argument.type
});

it('treats shorthand classes as the wrapper slot, applied before explicit slots', function (): void {
    $tailwind = new Tailwind(
        'rounded-2xl',
        'border',
        ['bg-white', 'shadow-sm'],
        new Slot(Tailwind::Wrapper, ['rounded-none']),
        new Slot(Tailwind::Header, ['px-6', 'py-4']),
    );

    expect($tailwind->slotNames())->toBe(['root', 'header'])
        ->and($tailwind->slotsFor(Tailwind::Wrapper))->toHaveCount(2)
        ->and($tailwind->apply(Tailwind::Wrapper, ClassList::from('p-4'))->toString())->toBe('p-4 rounded-none border bg-white shadow-sm')
        ->and($tailwind->apply(Tailwind::Header, ClassList::empty())->toString())->toBe('px-6 py-4')
        ->and($tailwind->has(Tailwind::Body))->toBeFalse()
        ->and($tailwind->apply(Tailwind::Body, ClassList::from('p-6'))->toString())->toBe('p-6');
});

it('supports several slots and remove-then-extend sequences', function (): void {
    $tailwind = new Tailwind(
        Slot::remove(Tailwind::Wrapper, ['shadow-sm']),
        new Slot(Tailwind::Wrapper, ['ring-1', 'ring-slate-200']),
    );

    expect($tailwind->apply(Tailwind::Wrapper, ClassList::from('rounded-lg shadow-sm'))->toString())->toBe('rounded-lg ring-1 ring-slate-200');
});

it('is an immutable configuration node, never a child', function (): void {
    $tailwind = new Tailwind('p-4');
    $component = new Component(new Property(Component::Name, 'box'), $tailwind);

    expect($component->childCount())->toBe(0)
        ->and($component->configurations()->first(Tailwind::class))->toBe($tailwind)
        ->and($tailwind->normalize())->toBe(['slots' => [['name' => 'root', 'mode' => 'merge', 'classes' => ['p-4']]]])
        ->and($component->normalize()['configurations'][0]['class'])->toBe(Tailwind::class)
        ->and(fn () => $tailwind->slots[] = new Slot('root'))->toThrow(Error::class);
});

it('rejects invalid Tailwind items', function (): void {
    expect(fn () => new Tailwind(['p-4', 3]))->toThrow(InvalidClassException::class) // @phpstan-ignore argument.type
        ->and(fn () => new Tailwind(wrapper: 'p-4'))->toThrow(InvalidSlotException::class, 'named argument');
});
