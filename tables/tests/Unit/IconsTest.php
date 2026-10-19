<?php

declare(strict_types=1);

use Gcego\Tables\Exceptions\InvalidTableException;
use Gcego\Tables\Support\Icons;

it('resolves semantic aliases, canonical names and legacy bare names through gcego/luminaries', function (string $name, string $canonical): void {
    expect((string) app(Icons::class)->svg($name))->toContain('data-gcego-icon="'.$canonical.'"');
})->with([
    'alias' => ['tables.filter', 'heroicon:funnel'],
    'canonical' => ['lucide:funnel', 'lucide:funnel'],
    'legacy bare name' => ['magnifying-glass', 'heroicon:magnifying-glass'],
    'shape Heroicons lacks' => ['circle', 'inline:circle'],
]);

it('accepts luminaries identifiers in icon properties', function (string $name): void {
    expect(preg_match(Icons::NamePattern, $name))->toBe(1);
})->with(['actions.edit', 'lucide:pencil', 'check']);

it('keeps registered path icons working and refuses silent replacement', function (): void {
    app(Icons::class)->register('school', 'M4 4h16v16H4z');

    expect((string) app(Icons::class)->svg('school'))->toContain('d="M4 4h16v16H4z"')
        ->and(fn () => app(Icons::class)->register('school', 'M0 0h1v1H0z'))->toThrow(InvalidTableException::class, 'override: true');
});
