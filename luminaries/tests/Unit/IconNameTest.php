<?php

declare(strict_types=1);

use Gcego\Luminaries\Exceptions\InvalidIconNameException;
use Gcego\Luminaries\IconName;

it('parses canonical, alias and bare identifiers', function (string $name, string $kind, ?string $provider, string $icon): void {
    $parsed = IconName::parse($name);

    expect($parsed->kind)->toBe($kind)
        ->and($parsed->provider)->toBe($provider)
        ->and($parsed->icon)->toBe($icon);
})->with([
    'canonical' => ['lucide:graduation-cap', IconName::Canonical, 'lucide', 'graduation-cap'],
    'variant provider' => ['heroicon-solid:academic-cap', IconName::Canonical, 'heroicon-solid', 'academic-cap'],
    'digit-led icon' => ['heroicon:squares-2x2', IconName::Canonical, 'heroicon', 'squares-2x2'],
    'alias' => ['actions.edit', IconName::Alias, null, 'actions.edit'],
    'nested alias' => ['navigation.breadcrumb-separator', IconName::Alias, null, 'navigation.breadcrumb-separator'],
    'bare' => ['pencil-square', IconName::Bare, null, 'pencil-square'],
]);

it('rejects identifiers that could reach the filesystem or markup', function (string $name): void {
    IconName::parse($name);
})->throws(InvalidIconNameException::class)->with([
    'traversal' => ['custom:../../etc/passwd'],
    'slash' => ['custom:icons/logo'],
    'absolute' => ['/etc/passwd'],
    'null byte' => ["custom:logo\0"],
    'markup' => ['<script>'],
    'attribute break' => ['x" onload="alert(1)'],
    'uppercase' => ['Lucide:Pencil'],
    'two colons' => ['a:b:c'],
    'empty' => [''],
    'trailing dot' => ['actions.'],
    'dot in canonical' => ['lucide:pen.cil'],
]);

it('does not echo control characters back in the error message', function (): void {
    expect(fn () => IconName::parse("bad\nname<b>"))->toThrow(InvalidIconNameException::class, '"bad\nname<b>"');
});
