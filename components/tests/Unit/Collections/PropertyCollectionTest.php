<?php

declare(strict_types=1);

use Gcego\Components\Collections\ConfigurationCollection;
use Gcego\Components\Collections\PropertyCollection;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Tests\Fixtures\OtherConfiguration;
use Gcego\Components\Tests\Fixtures\Style;

it('looks up properties by key', function (): void {
    $collection = new PropertyCollection(new Property('name', 'a'), new Property('label', 'A'));

    expect($collection->has('name'))->toBeTrue()
        ->and($collection->has('missing'))->toBeFalse()
        ->and($collection->get('label')?->value)->toBe('A')
        ->and($collection->get('missing'))->toBeNull()
        ->and($collection->value('name'))->toBe('a')
        ->and($collection->value('missing', 'fallback'))->toBe('fallback')
        ->and($collection->count())->toBe(2)
        ->and($collection->isEmpty())->toBeFalse();
});

it('distinguishes an explicit null from an absent key', function (): void {
    $collection = new PropertyCollection(new Property('label', null));

    expect($collection->has('label'))->toBeTrue()
        ->and($collection->value('label', 'fallback'))->toBeNull();
});

it('iterates in declaration order', function (): void {
    $collection = new PropertyCollection(new Property('b', 2), new Property('a', 1));

    expect(array_keys(iterator_to_array($collection)))->toBe(['b', 'a'])
        ->and($collection->keys())->toBe(['b', 'a'])
        ->and($collection->toArray())->toBe(['b' => 2, 'a' => 1])
        ->and(array_keys($collection->all()))->toBe(['b', 'a']);
});

it('rejects duplicate keys', function (): void {
    new PropertyCollection(new Property('a', 1), new Property('a', 2));
})->throws(DuplicatePropertyException::class, 'Property "a" is declared more than once.');

it('returns modified copies', function (): void {
    $original = new PropertyCollection(new Property('a', 1), new Property('b', 2));
    $with = $original->with(new Property('a', 10), new Property('c', 3));
    $without = $original->without('a');

    expect($with->toArray())->toBe(['a' => 10, 'b' => 2, 'c' => 3])
        ->and($without->toArray())->toBe(['b' => 2])
        ->and($original->toArray())->toBe(['a' => 1, 'b' => 2]);
});

it('filters configuration nodes by class', function (): void {
    $style = new Style('p-4');
    $other = new OtherConfiguration;
    $collection = new ConfigurationCollection($style, $other, new Style('m-2'));

    expect($collection->has(Style::class))->toBeTrue()
        ->and($collection->first(Style::class))->toBe($style)
        ->and($collection->ofClass(Style::class))->toHaveCount(2)
        ->and($collection->ofClass(OtherConfiguration::class))->toBe([$other])
        ->and($collection->count())->toBe(3)
        ->and(iterator_to_array($collection))->toBe($collection->all())
        ->and((new ConfigurationCollection)->has(Style::class))->toBeFalse()
        ->and((new ConfigurationCollection)->isEmpty())->toBeTrue();
});
