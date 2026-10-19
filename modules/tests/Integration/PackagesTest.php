<?php

declare(strict_types=1);

use Gcego\Core\Packages\PackageManager;

it('integrates with the sixteen GCEGO packages it builds on', function (): void {
    $descriptor = app(PackageManager::class)->descriptor('gcego/modules');
    $installed = array_map(static fn ($package): string => $package->id, app(PackageManager::class)->descriptors());

    expect($descriptor->requires)->toContain('gcego/pages', 'gcego/navigation', 'gcego/security', 'gcego/data', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists')
        ->and($installed)->toContain('gcego/modules', 'gcego/pages', 'gcego/runtime', 'gcego/chroma', 'gcego/widgets', 'gcego/layouts');
});

it('keeps its configuration cacheable', function (): void {
    $config = config('gcego-modules');

    expect(eval('return '.var_export($config, true).';'))->toBe($config);
});
