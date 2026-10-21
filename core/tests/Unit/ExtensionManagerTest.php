<?php

declare(strict_types=1);

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Tests\Fixtures\BootLog;
use Gcego\Core\Tests\Fixtures\FixtureExtension;

/**
 * @param  list<string>  $packages
 */
function extensionContext(array $packages = ['gcego/core']): BootstrapContext
{
    return new BootstrapContext(app(), GcegoConfig::defaults(), $packages, []);
}

it('registers extensions', function (): void {
    $extension = FixtureExtension::make('tables.export');
    $manager = new ExtensionManager;
    $manager->register($extension);
    $manager->register($extension);

    expect($manager->has('tables.export'))->toBeTrue()
        ->and($manager->get('tables.export'))->toBe($extension)
        ->and($manager->descriptors())->toHaveCount(1)
        ->and(array_map(fn (ExtensionDescriptor $descriptor): string => $descriptor->id, $manager->descriptors()))->toBe(['tables.export']);
});

it('rejects duplicate identifiers from different objects', function (): void {
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('tables.export'));
    $manager->register(FixtureExtension::make('tables.export'));
})->throws(ExtensionException::class, 'GCEGO extension "tables.export" is already registered');

it('reports unknown extensions', function (): void {
    (new ExtensionManager)->descriptor('tables.export');
})->throws(ExtensionException::class, 'GCEGO extension "tables.export" is not registered.');

it('activates in dependency order with deterministic ties', function (): void {
    $log = new BootLog;
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('zeta', requires: ['core.base'], log: $log));
    $manager->register(FixtureExtension::make('alpha', optional: ['zeta'], log: $log));
    $manager->register(FixtureExtension::make('core.base', log: $log));

    $manager->activate(extensionContext());

    expect($log->entries)->toBe(['extension:core.base', 'extension:zeta', 'extension:alpha'])
        ->and($manager->active())->toBe(['core.base', 'zeta', 'alpha'])
        ->and($manager->isActive('zeta'))->toBeTrue();
});

it('activates each extension once', function (): void {
    $log = new BootLog;
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('tables.export', log: $log));
    $manager->activate(extensionContext());
    $manager->activate(extensionContext());

    expect($log->entries)->toBe(['extension:tables.export']);
});

it('skips extensions whose packages are inactive, cascading to dependants', function (): void {
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('forms.table-filters', whenPackages: ['gcego/tables']));
    $manager->register(FixtureExtension::make('forms.table-presets', requires: ['forms.table-filters']));
    $manager->register(FixtureExtension::make('forms.core'));

    $manager->activate(extensionContext(['gcego/forms']));

    expect($manager->active())->toBe(['forms.core'])
        ->and($manager->skipped())->toBe([
            'forms.table-filters' => 'package gcego/tables is not active',
            'forms.table-presets' => 'required extension forms.table-filters is inactive',
        ]);
});

it('skips disabled extensions', function (): void {
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('tables.export'));
    $manager->register(FixtureExtension::make('tables.csv', optional: ['tables.export']));
    $manager->activate(extensionContext(), ['tables.export']);

    expect($manager->active())->toBe(['tables.csv'])
        ->and($manager->skipped())->toBe(['tables.export' => 'disabled in gcego.extensions.disabled']);
});

it('rejects missing, disabled and circular requirements', function (Closure $setup, string $message): void {
    $manager = new ExtensionManager;
    $disabled = $setup($manager);

    expect(fn () => $manager->activate(extensionContext(), $disabled))->toThrow(ExtensionException::class, $message);
})->with([
    'missing' => [function (ExtensionManager $manager): array {
        $manager->register(FixtureExtension::make('tables.csv', requires: ['tables.export']));

        return [];
    }, 'GCEGO extension "tables.csv" requires the extension(s) tables.export, which are not registered.'],
    'disabled' => [function (ExtensionManager $manager): array {
        $manager->register(FixtureExtension::make('tables.export'));
        $manager->register(FixtureExtension::make('tables.csv', requires: ['tables.export']));

        return ['tables.export'];
    }, 'GCEGO extension "tables.csv" requires "tables.export", which is disabled'],
    'circular' => [function (ExtensionManager $manager): array {
        $manager->register(FixtureExtension::make('a', requires: ['b']));
        $manager->register(FixtureExtension::make('b', optional: ['a']));

        return [];
    }, 'Circular GCEGO extension dependency: a -> b -> a.'],
]);

it('wraps activation failures and retries only what failed', function (): void {
    $log = new BootLog;
    $attempts = 0;
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('a', log: $log));
    $manager->register(FixtureExtension::make('b', log: $log, onActivate: function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new LogicException('not ready');
        }
    }));

    expect(fn () => $manager->activate(extensionContext()))
        ->toThrow(BootstrapException::class, 'GCEGO extension "b" failed to activate: not ready');

    $manager->activate(extensionContext());

    expect($log->entries)->toBe(['extension:a', 'extension:b', 'extension:b']);
});

it('plans without activating', function (): void {
    $log = new BootLog;
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('a', log: $log));

    expect($manager->plan([], []))->toBe(['order' => ['a'], 'skipped' => []])
        ->and($log->entries)->toBe([]);
});

it('rejects registration once locked', function (): void {
    $manager = new ExtensionManager;
    $manager->lock();

    expect($manager->isLocked())->toBeTrue()
        ->and(fn () => $manager->register(FixtureExtension::make('late')))->toThrow(ExtensionException::class, 'registered after the framework booted');
});

it('cascades package skips before validating remaining requirements in any registration order', function (bool $reverse): void {
    $extensions = [
        FixtureExtension::make('a', requires: ['missing', 'b']),
        FixtureExtension::make('b', requires: ['c']),
        FixtureExtension::make('c', whenPackages: ['gcego/tables']),
    ];
    $manager = new ExtensionManager;

    foreach ($reverse ? array_reverse($extensions) : $extensions as $extension) {
        $manager->register($extension);
    }

    expect($manager->plan([], ['gcego/core']))->toBe([
        'order' => [],
        'skipped' => [
            'a' => 'required extension b is inactive',
            'b' => 'required extension c is inactive',
            'c' => 'package gcego/tables is not active',
        ],
    ]);
})->with([false, true]);

it('rejects registrations during activation and restores registration after failure', function (): void {
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('a', onActivate: fn () => $manager->register(FixtureExtension::make('late'))));

    expect(fn () => $manager->activate(extensionContext()))->toThrow(BootstrapException::class, 'cannot be registered while extensions are initializing')
        ->and($manager->has('late'))->toBeFalse();

    $manager->register(FixtureExtension::make('recovery'));
    expect($manager->has('recovery'))->toBeTrue();
});

it('rejects recursive activation without repeating hooks', function (): void {
    $manager = new ExtensionManager;
    $manager->register(FixtureExtension::make('a', onActivate: fn () => $manager->activate(extensionContext())));

    expect(fn () => $manager->activate(extensionContext()))->toThrow(BootstrapException::class, 'Cannot activate GCEGO extensions recursively.');
});
