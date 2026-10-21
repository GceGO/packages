<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintCollection;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Exceptions\DuplicateBlueprintException;
use Gcego\Blueprints\Exceptions\UnknownBlueprintException;

it('keys blueprints by name in order', function (): void {
    $subjects = Blueprint::define(Name::make('subjects'));
    $courses = Blueprint::define(Name::make('courses'));
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
    new BlueprintCollection(Blueprint::define(Name::make('subjects')), Blueprint::define(Name::make('subjects'), Table::make('App\\Tables\\SubjectsTable')));
})->throws(DuplicateBlueprintException::class, 'A blueprint named "subjects"');

it('ignores the same instance twice', function (): void {
    $subjects = Blueprint::define(Name::make('subjects'));

    expect((new BlueprintCollection($subjects, $subjects))->count())->toBe(1);
});

it('reports unknown names with the known ones', function (): void {
    (new BlueprintCollection(Blueprint::define(Name::make('subjects'))))->resolve('courses');
})->throws(UnknownBlueprintException::class, 'No blueprint named "courses". Known blueprints: subjects.');

it('returns modified copies', function (): void {
    $original = new BlueprintCollection(Blueprint::define(Name::make('subjects')));
    $added = $original->with(Blueprint::define(Name::make('courses')));
    $removed = $added->without('subjects');
    $filtered = $added->filter(fn (BlueprintInterface $blueprint): bool => $blueprint->name() === 'courses');

    expect($original->names())->toBe(['subjects'])
        ->and($added->names())->toBe(['subjects', 'courses'])
        ->and($removed->names())->toBe(['courses'])
        ->and($filtered->names())->toBe(['courses'])
        ->and((new BlueprintCollection)->isEmpty())->toBeTrue()
        ->and(fn () => $added->with(Blueprint::define(Name::make('courses'))))->toThrow(DuplicateBlueprintException::class);
});
