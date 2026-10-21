<?php

declare(strict_types=1);

use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Packages\PackageDescriptor;

it('describes a package', function (): void {
    $descriptor = new PackageDescriptor('gcego/tables', '1.2.0', ['gcego/core'], ['gcego/forms'], ['tables'], 'Tables');

    expect($descriptor->toArray())->toBe([
        'id' => 'gcego/tables',
        'version' => '1.2.0',
        'requires' => ['gcego/core'],
        'optional' => ['gcego/forms'],
        'capabilities' => ['tables'],
        'description' => 'Tables',
        'versionConstraints' => [],
        'exclusiveCapabilities' => [],
    ])
        ->and($descriptor->hasCapability('tables'))->toBeTrue()
        ->and($descriptor->hasCapability('forms'))->toBeFalse()
        ->and($descriptor->withVersion('2.0.0')->version)->toBe('2.0.0')
        ->and($descriptor->version)->toBe('1.2.0');
});

it('removes duplicate dependency and capability entries', function (): void {
    $descriptor = new PackageDescriptor('gcego/tables', requires: ['gcego/core', 'gcego/core'], capabilities: ['tables', 'tables']);

    expect($descriptor->requires)->toBe(['gcego/core'])
        ->and($descriptor->capabilities)->toBe(['tables']);
});

it('rejects invalid package metadata', function (Closure $factory, string $message): void {
    expect($factory)->toThrow(PackageRegistrationException::class, $message);
})->with([
    'identifier' => [fn () => new PackageDescriptor('Tables'), 'Invalid GCEGO package identifier "Tables"'],
    'uppercase vendor' => [fn () => new PackageDescriptor('Gcego/tables'), 'Invalid GCEGO package identifier'],
    'empty version' => [fn () => new PackageDescriptor('gcego/tables', ' '), 'the version must not be empty'],
    'bad requirement' => [fn () => new PackageDescriptor('gcego/tables', requires: ['tables']), 'every "requires" entry must be a Composer package name'],
    'bad optional' => [fn () => new PackageDescriptor('gcego/tables', optional: [5]), 'every "optional" entry'],
    'self dependency' => [fn () => new PackageDescriptor('gcego/tables', requires: ['gcego/tables']), 'cannot depend on itself'],
    'required and optional' => [fn () => new PackageDescriptor('gcego/tables', requires: ['gcego/forms'], optional: ['gcego/forms']), 'cannot be both required and optional'],
    'capability' => [fn () => new PackageDescriptor('gcego/tables', capabilities: ['Tables Export']), 'capabilities must be lowercase dotted names'],
]);

it('describes an extension', function (): void {
    $descriptor = new ExtensionDescriptor('tables.export', ['tables.core'], ['tables.csv'], ['gcego/tables'], ['export'], 'Export');

    expect($descriptor->toArray())->toBe([
        'id' => 'tables.export',
        'requires' => ['tables.core'],
        'optional' => ['tables.csv'],
        'whenPackages' => ['gcego/tables'],
        'capabilities' => ['export'],
        'description' => 'Export',
        'requiresCapabilities' => [],
        'exclusiveCapabilities' => [],
    ])
        ->and($descriptor->hasCapability('export'))->toBeTrue();
});

it('rejects invalid extension metadata', function (Closure $factory, string $message): void {
    expect($factory)->toThrow(ExtensionException::class, $message);
})->with([
    'identifier' => [fn () => new ExtensionDescriptor('Tables Export'), 'Invalid GCEGO extension identifier'],
    'requires' => [fn () => new ExtensionDescriptor('a.b', requires: ['gcego/tables']), '"requires" entries must be extension identifiers'],
    'when packages' => [fn () => new ExtensionDescriptor('a.b', whenPackages: ['tables']), '"whenPackages" entries must be Composer package names'],
    'self dependency' => [fn () => new ExtensionDescriptor('a.b', optional: ['a.b']), 'cannot depend on itself'],
    'required and optional' => [fn () => new ExtensionDescriptor('a.b', requires: ['c'], optional: ['c']), 'cannot be both required and optional'],
    'capability' => [fn () => new ExtensionDescriptor('a.b', capabilities: ['']), 'capabilities must be lowercase dotted names'],
]);
