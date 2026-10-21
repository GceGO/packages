<?php

declare(strict_types=1);

it('depends on no other GCEGO package, not even for development', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $packages = [...array_keys($manifest['require'] ?? []), ...array_keys($manifest['require-dev'] ?? [])];

    expect(array_values(array_filter($packages, static fn (string $package): bool => str_starts_with($package, 'gcego/'))))->toBe([])
        ->and($manifest)->not->toHaveKey('repositories');
});

it('references no other GCEGO namespace from its code', function (string $directory): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$directory, FilesystemIterator::SKIP_DOTS));
    $violations = [];

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php'
            && preg_match('/Gcego\\\\\\\\?(?!Core\\\\)[A-Z]\w*\\\\/', (string) file_get_contents($file->getPathname())) === 1) {
            $violations[] = $file->getFilename();
        }
    }

    expect($violations)->toBe([]);
})->with(['src', 'config', 'tests', 'benchmarks']);
