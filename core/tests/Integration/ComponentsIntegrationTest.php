<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Property;
use Gcego\Core\Gcego;

it('instantiates and validates real gcego/components definitions inside a GCEGO application', function (): void {
    expect(app(Gcego::class)->isBooted())->toBeTrue();

    $dashboard = new Component(
        new Property(Component::Name, 'dashboard'),
        new Property(Component::Label, 'Dashboard'),
        new Component(new Property(Component::Name, 'statistics')),
    );

    expect($dashboard->assertValid())->toBe($dashboard)
        ->and($dashboard->tree()->find('dashboard.statistics'))->not->toBeNull()
        ->and($dashboard->normalize()['properties'])->toBe(['label' => 'Dashboard', 'name' => 'dashboard']);
});

it('keeps the dependency direction one-way', function (): void {
    $componentsSource = dirname(__DIR__, 2).'/vendor/gcego/components/src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($componentsSource, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            expect((string) file_get_contents($file->getPathname()))->not->toContain('Gcego\\Core');
        }
    }
});
