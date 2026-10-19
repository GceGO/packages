<?php

declare(strict_types=1);

use Gcego\Luminaries\Exceptions\InvalidConfigurationException;
use Gcego\Luminaries\LuminariesConfig;

it('applies presets in order and lets configured aliases win', function (): void {
    $config = LuminariesConfig::fromArray([
        'presets' => ['framework', 'education'],
        'aliases' => ['actions.edit' => 'lucide:pencil'],
        'sizes' => ['md' => 'size-5'],
    ]);

    expect($config->allAliases()['actions.edit'])->toBe(['target' => 'lucide:pencil', 'origin' => 'config'])
        ->and($config->allAliases()['resources.subjects'])->toBe(['target' => 'education.subject', 'origin' => 'education'])
        ->and($config->allAliases()['actions.delete'])->toBe(['target' => 'heroicon:trash', 'origin' => 'framework']);
});

it('skips disabled providers', function (): void {
    $config = LuminariesConfig::fromArray([
        'providers' => ['lucide' => ['enabled' => false, 'set' => 'lucide'], 'heroicon' => ['set' => 'heroicons', 'variant' => 'o']],
        'sizes' => ['md' => 'size-5'],
    ]);

    expect(array_keys($config->providers))->toBe(['heroicon']);
});

it('changes the fingerprint when aliases change', function (): void {
    $base = ['sizes' => ['md' => 'size-5']];

    expect(LuminariesConfig::fromArray($base)->fingerprint())
        ->not->toBe(LuminariesConfig::fromArray([...$base, 'aliases' => ['actions.edit' => 'lucide:pencil']])->fingerprint());
});

it('rejects invalid configuration with the offending key', function (array $config, string $message): void {
    expect(fn () => LuminariesConfig::fromArray([...['sizes' => ['md' => 'size-5']], ...$config]))->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'unknown preset' => [['presets' => ['fantasy']], 'luminaries.presets": unknown preset "fantasy"'],
    'unknown missing behavior' => [['missing' => ['behavior' => 'ignore']], 'luminaries.missing.behavior'],
    'invalid fallback' => [['missing' => ['behavior' => 'fallback', 'fallback' => '../x']], 'luminaries.missing.fallback'],
    'unknown default size' => [['default_size' => 'huge'], 'luminaries.default_size'],
    'class injection in size' => [['sizes' => ['md' => 'size-5" onload="x']], 'luminaries.sizes.md'],
    'class injection in tone' => [['tones' => ['primary' => '<b>']], 'luminaries.tones.primary'],
    'provider without set' => [['providers' => ['lucide' => ['label' => 'Lucide']]], 'luminaries.providers.lucide'],
    'collection without path' => [['collections' => ['brand' => ['label' => 'Brand']]], 'luminaries.collections.brand'],
    'collection shadowing a provider' => [['providers' => ['lucide' => ['set' => 'lucide']], 'collections' => ['lucide' => ['path' => '/tmp']]], 'already used by a provider'],
    'non-string alias target' => [['aliases' => ['actions.edit' => ['lucide:pencil']]], 'luminaries.aliases.actions.edit'],
    'explorer path' => [['explorer' => ['path' => '../admin?x=1']], 'luminaries.explorer.path'],
]);
