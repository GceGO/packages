<?php

declare(strict_types=1);

use Gcego\Blueprints\Definitions\Actions;
use Gcego\Blueprints\Definitions\Derive;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Relations;
use Gcego\Blueprints\Definitions\Widgets;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;

it('stores a list of class references in declaration order', function (string $class, string $key): void {
    $definition = $class::make(['App\\Second', 'App\\First']);

    expect($definition->key())->toBe($key)
        ->and($definition->value())->toBe(['App\\Second', 'App\\First'])
        ->and($definition->classes())->toBe(['App\\Second', 'App\\First'])
        ->and($definition)->toHaveCount(2);
})->with([
    'pages' => [Pages::class, 'pages'],
    'actions' => [Actions::class, 'actions'],
    'relations' => [Relations::class, 'relations'],
    'widgets' => [Widgets::class, 'widgets'],
    'derive' => [Derive::class, 'derive'],
]);

it('normalizes leading backslashes of every element', function (): void {
    expect(Pages::make(['\\App\\Pages\\ListSubjects', 'App\\Pages\\EditSubject'])->value())
        ->toBe(['App\\Pages\\ListSubjects', 'App\\Pages\\EditSubject']);
});

it('finds a listed class regardless of case and leading backslash', function (): void {
    $pages = Pages::make(['App\\Pages\\ListSubjects']);

    expect($pages->contains('\\app\\pages\\listsubjects'))->toBeTrue()
        ->and($pages->contains('App\\Pages\\EditSubject'))->toBeFalse();
});

it('rejects an associative array', function (): void {
    /** @phpstan-ignore argument.type (the runtime check is under test) */
    Pages::make(['list' => 'App\\Pages\\ListSubjects']);
})->throws(InvalidDefinitionException::class, "expected a list of class names, got an array keyed by 'list'.");

it('rejects a list with gaps in its keys', function (): void {
    /** @phpstan-ignore argument.type (the runtime check is under test) */
    Actions::make([1 => 'App\\Actions\\DeleteSubject']);
})->throws(InvalidDefinitionException::class, 'expected a list of class names');

it('rejects an empty list', function (): void {
    Widgets::make([]);
})->throws(InvalidDefinitionException::class, 'Invalid "widgets" definition: expected at least one class name.');

it('rejects an element that is not a string, naming its position and type', function (mixed $element, string $type): void {
    /** @phpstan-ignore argument.type (the runtime check is under test) */
    expect(fn () => Relations::make(['App\\Relations\\TopicsRelation', $element]))
        ->toThrow(InvalidDefinitionException::class, 'Invalid "relations" definition: element 1: expected a class name such as App\\Models\\Subject::class, got '.$type.'.');
})->with([
    'integer' => [42, 'int'],
    'null' => [null, 'null'],
    'object' => [new stdClass, 'stdClass'],
    'nested list' => [['App\\Relations\\SyllabiRelation'], 'array'],
]);

it('rejects an element that is not a class name', function (): void {
    Pages::make(['App\\Pages\\ListSubjects', 'list-subjects']);
})->throws(InvalidDefinitionException::class, 'element 1: "list-subjects" is not a valid class name.');

it('rejects a class listed twice, ignoring case and leading backslash', function (): void {
    Pages::make(['App\\Pages\\ListSubjects', 'App\\Pages\\EditSubject', '\\app\\pages\\LISTSUBJECTS']);
})->throws(InvalidDefinitionException::class, 'Invalid "pages" definition: app\\pages\\LISTSUBJECTS is listed twice (elements 0 and 2).');
