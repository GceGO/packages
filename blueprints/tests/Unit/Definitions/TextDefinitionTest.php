<?php

declare(strict_types=1);

use Gcego\Blueprints\Definitions\Description;
use Gcego\Blueprints\Definitions\Icon;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;

it('stores trimmed text under its key', function (string $class, string $key, string $input, string $value): void {
    $definition = $class::make($input);

    expect($definition->key())->toBe($key)
        ->and($class::definitionKey())->toBe($key)
        ->and($definition->value())->toBe($value);
})->with([
    'name' => [Name::class, 'name', ' academic-subjects ', 'academic-subjects'],
    'title' => [Title::class, 'title', ' Subject Management ', 'Subject Management'],
    'icon' => [Icon::class, 'icon', ' lucide:book-open ', 'lucide:book-open'],
    'description' => [Description::class, 'description', "Manage academic subjects.\nIncludes syllabi.", "Manage academic subjects.\nIncludes syllabi."],
]);

it('rejects a name that is not a lowercase identifier', function (string $name): void {
    Name::make($name);
})->throws(InvalidDefinitionException::class, 'Invalid "name" definition')->with([
    'empty' => [''],
    'blank' => ['   '],
    'uppercase' => ['Subjects'],
    'spaces' => ['academic subjects'],
    'leading digit' => ['1subjects'],
    'dotted' => ['academics.subjects'],
    'too long' => [str_repeat('a', 101)],
]);

it('suggests the normalized spelling of a rejected name', function (): void {
    expect(fn () => Name::make('Academic Subjects'))
        ->toThrow(InvalidDefinitionException::class, 'Did you mean "academic-subjects"?');
});

it('rejects an empty or overlong title', function (string $title): void {
    Title::make($title);
})->throws(InvalidDefinitionException::class, 'Invalid "title" definition: expected a non-empty title of at most 200 characters.')->with([
    'empty' => [''],
    'blank' => [" \t "],
    'too long' => [str_repeat('x', 201)],
]);

it('rejects empty, overlong or control-character descriptions', function (string $description): void {
    Description::make($description);
})->throws(InvalidDefinitionException::class, 'Invalid "description" definition')->with([
    'empty' => [''],
    'too long' => [str_repeat('x', 1001)],
    'control character' => ["Manage\x07subjects"],
]);

it('reports the key and reason of an invalid value', function (): void {
    try {
        Title::make('');
    } catch (InvalidDefinitionException $exception) {
        expect($exception->key)->toBe('title')
            ->and($exception->reason)->toBe('expected a non-empty title of at most 200 characters.');

        return;
    }

    $this->fail('Title::make("") did not throw.');
});

it('rejects an icon that is not an icon identifier', function (string $icon): void {
    Icon::make($icon);
})->throws(InvalidDefinitionException::class, 'is not an icon identifier such as "academic-cap" or "lucide:book-open".')->with([
    'empty' => [''],
    'uppercase' => ['AcademicCap'],
    'spaces' => ['academic cap'],
]);
