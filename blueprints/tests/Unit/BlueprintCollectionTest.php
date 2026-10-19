<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintCollection;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Blueprints\Exceptions\DuplicateBlueprintException;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Exceptions\UnknownBlueprintException;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;

it('keys blueprints by name in order', function (): void {
    $subjects = new Blueprint('subjects');
    $courses = new Blueprint('courses');
    $collection = new BlueprintCollection($subjects, $courses);

    expect($collection->names())->toBe(['subjects', 'courses'])
        ->and($collection->has('courses'))->toBeTrue()
        ->and($collection->get('courses'))->toBe($courses)
        ->and($collection->get('missing'))->toBeNull()
        ->and($collection->resolve('subjects'))->toBe($subjects)
        ->and($collection->count())->toBe(2)
        ->and(array_keys(iterator_to_array($collection)))->toBe(['subjects', 'courses'])
        ->and($collection->toArray())->toBe([$subjects, $courses])
        ->and($collection)->toBeInstanceOf(BlueprintResolver::class);
});

it('rejects different blueprints with the same name', function (): void {
    new BlueprintCollection(new Blueprint('subjects'), new Blueprint('subjects', new ExampleTable('table')));
})->throws(DuplicateBlueprintException::class, 'A blueprint named "subjects"');

it('ignores the same instance twice', function (): void {
    $subjects = new Blueprint('subjects');

    expect((new BlueprintCollection($subjects, $subjects))->count())->toBe(1);
});

it('rejects unnamed blueprints', function (): void {
    new BlueprintCollection(new Blueprint);
})->throws(InvalidBlueprintException::class);

it('reports unknown names with the known ones', function (): void {
    (new BlueprintCollection(new Blueprint('subjects')))->resolve('courses');
})->throws(UnknownBlueprintException::class, 'No blueprint named "courses". Known blueprints: subjects.');

it('returns modified copies', function (): void {
    $original = new BlueprintCollection(new Blueprint('subjects'));
    $added = $original->with(new Blueprint('courses'));
    $removed = $added->without('subjects');
    $filtered = $added->filter(fn (BlueprintInterface $blueprint): bool => $blueprint->name() === 'courses');

    expect($original->names())->toBe(['subjects'])
        ->and($added->names())->toBe(['subjects', 'courses'])
        ->and($removed->names())->toBe(['courses'])
        ->and($filtered->names())->toBe(['courses'])
        ->and((new BlueprintCollection)->isEmpty())->toBeTrue()
        ->and(fn () => $added->with(new Blueprint('courses')))->toThrow(DuplicateBlueprintException::class);
});
