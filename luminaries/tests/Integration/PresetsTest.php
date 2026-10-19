<?php

declare(strict_types=1);

use Gcego\Luminaries\Facades\Luminaries;
use Gcego\Luminaries\Icons\Alias;
use Gcego\Luminaries\Presets\EducationPreset;
use Gcego\Luminaries\Presets\FrameworkPreset;

it('resolves every framework and education alias to an installed icon', function (): void {
    configureLuminaries(['presets' => ['framework', 'education']]);
    $broken = [];

    foreach ([...FrameworkPreset::Aliases, ...EducationPreset::Aliases] as $alias => $target) {
        if (! Luminaries::exists($alias)) {
            $broken[] = "{$alias} => {$target}";
        }
    }

    expect($broken)->toBe([]);
});

it('keeps educational concepts out of the framework preset', function (): void {
    expect(array_keys(FrameworkPreset::Aliases))->each->not->toMatch('/^(education|resources)\.|students|academics/');
});

it('covers the semantic groups the framework components rely on', function (): void {
    $groups = array_unique(array_map(static fn (string $alias): string => explode('.', $alias)[0], array_keys(FrameworkPreset::Aliases)));

    expect($groups)->toContain('navigation', 'actions', 'status', 'authentication', 'notifications', 'tables', 'forms', 'media', 'workflows', 'analytics', 'settings', 'panels', 'layouts');
});

it('has one Alias case per preset alias, each resolving to an installed icon', function (): void {
    configureLuminaries(['presets' => ['framework', 'education']]);

    expect(array_map(static fn (Alias $alias): string => $alias->value, Alias::cases()))->toBe([...array_keys(FrameworkPreset::Aliases), ...array_keys(EducationPreset::Aliases)])
        ->and(array_filter(Alias::cases(), static fn (Alias $alias): bool => ! Luminaries::exists($alias)))->toBe([]);
});
