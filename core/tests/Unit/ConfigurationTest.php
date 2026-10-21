<?php

declare(strict_types=1);

use Gcego\Core\Configuration\ConfigurationValidator;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Exceptions\ConfigurationException;

it('provides defaults', function (): void {
    expect(GcegoConfig::defaults()->toArray())->toBe([
        'enabled' => true,
        'name' => 'GCEGO Admin Studio',
        'auto_boot' => true,
        'environment_checks' => true,
        'packages' => ['disabled' => []],
        'extensions' => ['disabled' => []],
        'diagnostics' => ['profiling' => false, 'slow_boot_ms' => 100.0, 'slow_package_ms' => 50.0, 'slow_extension_ms' => 50.0],
    ]);
});

it('reads overrides', function (): void {
    $config = GcegoConfig::fromArray([
        'enabled' => false,
        'name' => '  School Admin ',
        'auto_boot' => false,
        'environment_checks' => false,
        'packages' => ['disabled' => ['gcego/analytics']],
        'extensions' => ['disabled' => ['tables.export', 'tables.export']],
    ]);

    expect($config->enabled())->toBeFalse()
        ->and($config->name())->toBe('School Admin')
        ->and($config->autoBoot())->toBeFalse()
        ->and($config->environmentChecks())->toBeFalse()
        ->and($config->disabledPackages())->toBe(['gcego/analytics'])
        ->and($config->isPackageDisabled('gcego/analytics'))->toBeTrue()
        ->and($config->isPackageDisabled('gcego/tables'))->toBeFalse()
        ->and($config->disabledExtensions())->toBe(['tables.export'])
        ->and($config->isExtensionDisabled('tables.export'))->toBeTrue();
});

it('interprets booleans left behind by env()', function (mixed $value, bool $expected): void {
    expect(GcegoConfig::fromArray(['enabled' => $value])->enabled())->toBe($expected);
})->with([
    [true, true], [false, false], [1, true], [0, false],
    ['1', true], ['0', false], ['true', true], ['false', false],
    ['yes', true], ['off', false], ['TRUE', true],
]);

it('treats null and empty strings as the default', function (mixed $value): void {
    $config = GcegoConfig::fromArray(['enabled' => $value, 'auto_boot' => $value, 'name' => null, 'packages' => ['disabled' => null]]);

    expect($config->enabled())->toBeTrue()
        ->and($config->autoBoot())->toBeTrue()
        ->and($config->name())->toBe('GCEGO Admin Studio')
        ->and($config->disabledPackages())->toBe([]);
})->with([null, '']);

it('rejects invalid values listing every problem without echoing values', function (): void {
    try {
        GcegoConfig::fromArray([
            'enabled' => 'sometimes',
            'name' => ['secret-token'],
            'auto_boot' => 2,
            'packages' => ['disabled' => ['Not A Package']],
            'extensions' => 'none',
        ]);
        $this->fail('Expected a configuration exception.');
    } catch (ConfigurationException $exception) {
        expect($exception->getMessage())
            ->toContain('gcego.enabled must be a boolean, a string given.')
            ->toContain('gcego.name must be a non-empty string of at most 120 characters, an array given.')
            ->toContain('gcego.auto_boot must be a boolean, int given.')
            ->toContain('gcego.packages.disabled.0 must be one of Composer package names such as "gcego/tables", a string given.')
            ->toContain('gcego.extensions must be an array, a string given.')
            ->not->toContain('sometimes')
            ->not->toContain('secret-token')
            ->not->toContain('Not A Package');
    }
});

it('rejects empty and overlong names', function (string $name): void {
    GcegoConfig::fromArray(['name' => $name]);
})->with(['   ', str_repeat('x', 121)])->throws(ConfigurationException::class, 'gcego.name');

it('requires disabled entries to be lists', function (): void {
    GcegoConfig::fromArray(['extensions' => ['disabled' => ['a' => 'tables.export']]]);
})->throws(ConfigurationException::class, 'gcego.extensions.disabled must be a list of extension identifiers');

it('warns about unknown keys instead of failing', function (): void {
    $result = (new ConfigurationValidator)->validate(['debug' => true, 'packages' => ['auto_boot' => true]]);

    expect($result['errors'])->toBe([])
        ->and($result['warnings'])->toBe([
            'gcego.debug is not a known option and is ignored.',
            'gcego.packages.auto_boot is not a known option and is ignored.',
        ]);
});

it('validates profiling settings and finite positive thresholds', function (mixed $value): void {
    expect(fn () => GcegoConfig::fromArray(['diagnostics' => ['slow_package_ms' => $value]]))->toThrow(ConfigurationException::class, 'positive finite number');
})->with([[0], [-1], [INF], [NAN], ['secret'], [true], [[]]]);

it('accepts explicit profiling options and thresholds', function (): void {
    $config = GcegoConfig::fromArray(['diagnostics' => ['profiling' => true, 'slow_boot_ms' => 200, 'slow_package_ms' => 25.5, 'slow_extension_ms' => 10]]);
    expect($config->profiling())->toBeTrue()
        ->and($config->slowBootMs())->toBe(200.0)
        ->and($config->slowPackageMs())->toBe(25.5)
        ->and($config->slowExtensionMs())->toBe(10.0);
});
