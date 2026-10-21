<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\ComponentException;
use Gcego\Components\Exceptions\InvalidPropertyException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Tests\Fixtures\Alignment;
use Gcego\Components\Tests\Fixtures\Callback;
use Gcego\Components\Tests\Fixtures\Money;
use Gcego\Components\Tests\Fixtures\Status;

it('stores a key and value', function (): void {
    $property = new Property(Component::Name, 'subjects');

    expect($property->key)->toBe('name')
        ->and($property->value)->toBe('subjects');
});

it('accepts valid keys', function (string $key): void {
    expect((new Property($key, 1))->key)->toBe($key);
})->with(['name', 'perPage', '_internal', 'data.source', 'aria-label', 'v2']);

it('rejects invalid keys', function (string $key): void {
    new Property($key, 1);
})->with(['', '1name', 'has space', 'semi;colon', str_repeat('a', 129)])
    ->throws(InvalidPropertyException::class, 'Invalid property key');

it('accepts supported values', function (mixed $value): void {
    expect((new Property('value', $value))->value)->toBe($value);
})->with([
    'null' => [null],
    'string' => ['text'],
    'int' => [42],
    'float' => [1.5],
    'bool' => [true],
    'array' => [['a' => 1, 'b' => [true, null]]],
    'backed enum' => [Status::Published],
    'unit enum' => [Alignment::End],
    'class string' => [Component::class],
]);

it('accepts value objects and runtime values', function (): void {
    $money = new Money(100, 'XAF');
    $callback = new Callback(static fn (): int => 1);

    expect((new Property('price', $money))->value)->toBe($money)
        ->and((new Property('formatter', $callback))->value)->toBe($callback);
});

it('rejects closures with a hint about RuntimeValue', function (): void {
    new Property('formatter', static fn (): int => 1);
})->throws(InvalidPropertyException::class, 'RuntimeValue');

it('rejects arbitrary objects', function (): void {
    new Property('service', new ArrayObject);
})->throws(InvalidPropertyException::class, 'value is ArrayObject');

it('rejects components as property values', function (): void {
    new Property('child', new Component);
})->throws(InvalidPropertyException::class, 'Child components must be passed as constructor items');

it('reports the location of unsupported nested values', function (): void {
    new Property('options', ['ok' => 1, 'nested' => [new stdClass]]);
})->throws(InvalidPropertyException::class, 'value[nested][0] is stdClass');

it('rejects arrays nested too deeply', function (): void {
    $value = 'leaf';

    for ($level = 0; $level < 40; $level++) {
        $value = [$value];
    }

    new Property('deep', $value);
})->throws(InvalidPropertyException::class, 'nesting deeper than 32 levels');

it('is immutable', function (): void {
    $property = new Property('name', 'subjects');

    expect(fn () => $property->value = 'changed')->toThrow(Error::class);
});

it('copies arrays so external mutation cannot leak in', function (): void {
    $options = ['a' => 1];
    $property = new Property('options', $options);
    $options['a'] = 2;

    expect($property->value)->toBe(['a' => 1]);
});

it('throws exceptions implementing the package marker interface', function (): void {
    try {
        new Property('', 1);
        $this->fail('Expected an exception.');
    } catch (ComponentException $exception) {
        expect($exception)->toBeInstanceOf(InvalidPropertyException::class);
    }
});

it('reports whether a value is supported', function (): void {
    expect(Property::isSupportedValue(['x' => Status::Draft]))->toBeTrue()
        ->and(Property::isSupportedValue(new stdClass))->toBeFalse();
});
