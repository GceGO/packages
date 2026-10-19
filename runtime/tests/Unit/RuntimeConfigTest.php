<?php

declare(strict_types=1);

use Gcego\Runtime\Exceptions\InvalidRenderContextException;
use Gcego\Runtime\RuntimeConfig;

it('reads the shipped defaults', function (): void {
    $config = RuntimeConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-runtime.php');

    expect($config->strict)->toBeTrue()
        ->and($config->validate)->toBeTrue()
        ->and($config->maxDepth)->toBe(64)
        ->and($config->livewireEnabled)->toBeTrue()
        ->and($config->livewireAlias)->toBe('gcego-blueprint')
        ->and($config->ability)->toBe('gcego-runtime.render')
        ->and($config->debug)->toBeFalse();
});

it('rejects invalid values', function (array $config, string $message): void {
    expect(fn () => RuntimeConfig::fromArray($config))->toThrow(InvalidRenderContextException::class, $message);
})->with([
    [['rendering' => ['max_depth' => 0]], 'max_depth'],
    [['rendering' => ['strict' => 'maybe']], 'rendering.strict'],
    [['livewire' => ['alias' => 'Gcego Blueprint']], 'livewire.alias'],
    [['authorization' => ['ability' => '']], 'authorization.ability'],
]);
