<?php

declare(strict_types=1);

use Gcego\Luminaries\Exceptions\DuplicateRegistrationException;
use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\Providers\InlinePathProvider;
use Gcego\Luminaries\Registry\IconRegistry;

it('follows alias chains to the first non-alias target', function (): void {
    $registry = new IconRegistry;
    $registry->alias('status.error', 'heroicon:x-circle');
    $registry->alias('status.danger', 'status.error');

    expect($registry->chain('status.danger'))->toBe(['chain' => ['status.danger', 'status.error'], 'target' => 'heroicon:x-circle']);
});

it('reports an undefined alias in the chain with a null target', function (): void {
    $registry = new IconRegistry;
    $registry->alias('forms.show-password', 'authentication.show-password');

    expect($registry->chain('forms.show-password'))->toBe(['chain' => ['forms.show-password', 'authentication.show-password'], 'target' => null]);
});

it('refuses alias cycles and keeps the previous mapping', function (): void {
    $registry = new IconRegistry;
    $registry->alias('a.one', 'a.two');
    $registry->alias('a.two', 'heroicon:plus');

    expect(fn () => $registry->alias('a.two', 'a.one', override: true))->toThrow(InvalidConfigurationException::class, 'alias cycle detected')
        ->and($registry->target('a.two'))->toBe('heroicon:plus');
});

it('refuses chains deeper than the maximum depth', function (): void {
    $registry = new IconRegistry;
    $registry->alias('chain.a9', 'heroicon:plus');

    for ($index = 8; $index > 1; $index--) {
        $registry->alias("chain.a{$index}", 'chain.a'.($index + 1));
    }

    expect(fn () => $registry->alias('chain.a1', 'chain.a2'))->toThrow(InvalidConfigurationException::class, 'deeper than 8')
        ->and($registry->hasAlias('chain.a1'))->toBeFalse();
});

it('refuses to remap an alias without override', function (): void {
    $registry = new IconRegistry;
    $registry->alias('actions.edit', 'heroicon:pencil-square');

    expect(fn () => $registry->alias('actions.edit', 'lucide:pencil'))->toThrow(DuplicateRegistrationException::class, 'already mapped to "heroicon:pencil-square"');
});

it('accepts re-registering the same mapping', function (): void {
    $registry = new IconRegistry;
    $registry->alias('actions.edit', 'heroicon:pencil-square');

    $registry->alias('actions.edit', 'heroicon:pencil-square');

    expect($registry->target('actions.edit'))->toBe('heroicon:pencil-square');
});

it('refuses duplicate providers', function (): void {
    $registry = new IconRegistry;
    $registry->addProvider(new InlinePathProvider);

    $registry->addProvider(new InlinePathProvider);
})->throws(DuplicateRegistrationException::class, 'already registered');

it('refuses malformed aliases and targets', function (string $alias, string $target, string $message): void {
    expect(fn () => (new IconRegistry)->alias($alias, $target))->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'bare alias' => ['edit', 'heroicon:pencil', 'not a valid alias'],
    'traversal target' => ['actions.edit', '../pencil', 'not a valid icon identifier'],
]);
