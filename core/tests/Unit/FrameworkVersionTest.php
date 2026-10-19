<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Gcego\Core\Support\FrameworkVersion;

it('reads the version from Composer metadata', function (): void {
    $version = FrameworkVersion::detect('gcego/core');

    expect($version->package)->toBe('gcego/core')
        ->and($version->version)->toBe(InstalledVersions::getPrettyVersion('gcego/core'))
        ->and((string) $version)->toBe($version->version)
        ->and($version->toString())->toBe($version->version);
});

it('falls back to a development marker for unknown packages', function (): void {
    $version = FrameworkVersion::detect('gcego/not-installed');

    expect($version->version)->toBe(FrameworkVersion::Unknown)
        ->and($version->reference)->toBeNull()
        ->and($version->isDevelopment())->toBeTrue();
});

it('recognizes development and release versions', function (string $version, bool $development): void {
    expect((new FrameworkVersion('gcego/core', $version))->isDevelopment())->toBe($development);
})->with([
    ['dev-main', true],
    ['1.x-dev', true],
    ['1.0.0+no-version-set', true],
    ['dev', true],
    ['1.2.0', false],
    ['v2.0.0-beta.1', false],
]);
