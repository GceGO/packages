<?php

declare(strict_types=1);

use Gcego\Components\Evaluation\StatePath;

it('resolves Filament-style relative and absolute state paths', function (string $container, string $path, bool $absolute, string $resolved): void {
    expect(StatePath::resolve($container, $path, $absolute))->toBe($resolved);
})->with([
    'sibling' => ['data.address', 'city', false, 'data.address.city'],
    'parent sibling' => ['data.address', '../name', false, 'data.name'],
    'two levels up' => ['data.address', '../../name', false, 'name'],
    'parent itself' => ['data.address', '..', false, 'data'],
    'container itself' => ['data.address', '', false, 'data.address'],
    'beyond the root' => ['data', '../../name', false, 'name'],
    'absolute' => ['data.address', 'data.type', true, 'data.type'],
    'root container' => ['', 'name', false, 'name'],
]);
