<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\BootLog;
use Gcego\Core\Tests\Fixtures\FixturePackage;
use Gcego\Core\Tests\Fixtures\PlainPackage;

/**
 * @param  list<string>  $packages
 */
function packageContext(array $packages = []): BootstrapContext
{
    return new BootstrapContext(app(), GcegoConfig::defaults(), $packages, []);
}

it('registers packages and exposes their descriptors in registration order', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/tables'));
    $manager->register(FixturePackage::make('gcego/forms'));

    expect($manager->has('gcego/tables'))->toBeTrue()
        ->and($manager->has('gcego/missing'))->toBeFalse()
        ->and(array_map(fn (PackageDescriptor $descriptor): string => $descriptor->id, $manager->descriptors()))->toBe(['gcego/tables', 'gcego/forms'])
        ->and($manager->descriptor('gcego/forms')->id)->toBe('gcego/forms');
});

it('reads descriptors once', function (): void {
    $package = FixturePackage::make('gcego/tables');
    $manager = new PackageManager;
    $manager->register($package);
    $manager->descriptors();
    $manager->descriptor('gcego/tables');

    expect($package->descriptorCalls)->toBe(1);
});

it('ignores repeated registration of the same instance', function (): void {
    $package = FixturePackage::make('gcego/tables');
    $manager = new PackageManager;
    $manager->register($package);
    $manager->register($package);

    expect($manager->descriptors())->toHaveCount(1)
        ->and($manager->get('gcego/tables'))->toBe($package);
});

it('refuses silent replacement of a registered package', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/tables'));
    $manager->register(new PlainPackage(new PackageDescriptor('gcego/tables')));
})->throws(PackageRegistrationException::class, 'GCEGO package "gcego/tables" is already registered by Gcego\Core\Tests\Fixtures\FixturePackage; Gcego\Core\Tests\Fixtures\PlainPackage cannot replace it.');

it('reports unknown packages', function (): void {
    (new PackageManager)->get('gcego/missing');
})->throws(PackageRegistrationException::class, 'GCEGO package "gcego/missing" is not registered.');

it('fills missing versions from Composer metadata', function (): void {
    $manager = new PackageManager;
    $manager->register(new PlainPackage(new PackageDescriptor('pestphp/pest')));
    $manager->register(new PlainPackage(new PackageDescriptor('gcego/not-installed')));
    $manager->register(new PlainPackage(new PackageDescriptor('gcego/tagged', '3.1.0')));

    expect($manager->descriptor('pestphp/pest')->version)->toBe(InstalledVersions::getPrettyVersion('pestphp/pest'))
        ->and($manager->descriptor('gcego/not-installed')->version)->toBeNull()
        ->and($manager->descriptor('gcego/tagged')->version)->toBe('3.1.0');
});

it('resolves a deterministic dependency order', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/pages', requires: ['gcego/tables', 'gcego/forms']));
    $manager->register(FixturePackage::make('gcego/tables', requires: ['gcego/core']));
    $manager->register(FixturePackage::make('gcego/forms', requires: ['gcego/core'], optional: ['gcego/tables']));
    $manager->register(FixturePackage::make('gcego/core'));

    expect($manager->resolve())->toBe(['gcego/core', 'gcego/tables', 'gcego/forms', 'gcego/pages']);
});

it('reports missing dependencies with an install hint', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/tables', requires: ['gcego/core', 'gcego/data']));
    $manager->resolve();
})->throws(PackageDependencyException::class, 'GCEGO package "gcego/tables" requires "gcego/core", "gcego/data", which are not registered. Install it with "composer require gcego/core gcego/data"');

it('ignores missing optional dependencies', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/forms', optional: ['gcego/tables']));

    expect($manager->resolve())->toBe(['gcego/forms']);
});

it('excludes disabled packages and rejects dependants of them', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/tables'));
    $manager->register(FixturePackage::make('gcego/forms', optional: ['gcego/tables']));

    expect($manager->resolve(['gcego/tables']))->toBe(['gcego/forms']);

    $manager->register(FixturePackage::make('gcego/pages', requires: ['gcego/tables']));

    expect(fn () => $manager->resolve(['gcego/tables']))
        ->toThrow(PackageDependencyException::class, 'GCEGO package "gcego/pages" requires "gcego/tables", which is disabled in gcego.packages.disabled.');
});

it('detects circular dependencies', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/a', requires: ['gcego/b']));
    $manager->register(FixturePackage::make('gcego/b', requires: ['gcego/c']));
    $manager->register(FixturePackage::make('gcego/c', optional: ['gcego/a']));
    $manager->resolve();
})->throws(PackageDependencyException::class, 'Circular GCEGO package dependency: gcego/a -> gcego/b -> gcego/c -> gcego/a.');

it('collects every dependency problem for diagnostics', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/a', requires: ['gcego/b', 'gcego/missing']));
    $manager->register(FixturePackage::make('gcego/b', requires: ['gcego/a']));

    expect($manager->problems())->toHaveCount(2)
        ->and((new PackageManager)->problems())->toBe([]);
});

it('boots bootable packages in the given order exactly once', function (): void {
    $log = new BootLog;
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/tables', log: $log));
    $manager->register(FixturePackage::make('gcego/core', log: $log));
    $manager->register(new PlainPackage(new PackageDescriptor('gcego/plain')));

    $manager->boot(['gcego/core', 'gcego/plain', 'gcego/tables'], packageContext());
    $manager->boot(['gcego/core', 'gcego/plain', 'gcego/tables'], packageContext());

    expect($log->entries)->toBe(['package:gcego/core', 'package:gcego/tables'])
        ->and($manager->booted())->toBe(['gcego/core', 'gcego/plain', 'gcego/tables'])
        ->and($manager->isBooted('gcego/plain'))->toBeTrue();
});

it('wraps boot failures and resumes after the failed package on retry', function (): void {
    $log = new BootLog;
    $attempts = 0;
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('gcego/core', log: $log));
    $manager->register(FixturePackage::make('gcego/tables', log: $log, onBoot: function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('database unavailable');
        }
    }));

    try {
        $manager->boot(['gcego/core', 'gcego/tables'], packageContext());
        $this->fail('Expected a boot failure.');
    } catch (BootstrapException $exception) {
        expect($exception->getMessage())->toBe('GCEGO package "gcego/tables" failed to boot: database unavailable')
            ->and($exception->getPrevious())->toBeInstanceOf(RuntimeException::class)
            ->and($manager->isBooted('gcego/core'))->toBeTrue()
            ->and($manager->isBooted('gcego/tables'))->toBeFalse();
    }

    $manager->boot(['gcego/core', 'gcego/tables'], packageContext());

    expect($log->entries)->toBe(['package:gcego/core', 'package:gcego/tables', 'package:gcego/tables'])
        ->and($manager->isBooted('gcego/tables'))->toBeTrue();
});

it('rejects new registrations once locked', function (): void {
    $package = FixturePackage::make('gcego/tables');
    $manager = new PackageManager;
    $manager->register($package);
    $manager->lock();
    $manager->register($package);

    expect($manager->isLocked())->toBeTrue()
        ->and(fn () => $manager->register(FixturePackage::make('gcego/late')))
        ->toThrow(PackageRegistrationException::class, 'registered after package registration was locked');
});

it('rejects registrations during package boot and restores registration after failure', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('acme/first', onBoot: fn () => $manager->register(FixturePackage::make('acme/late'))));

    expect(fn () => $manager->boot(['acme/first'], packageContext()))->toThrow(BootstrapException::class, 'cannot be registered while packages are initializing')
        ->and($manager->has('acme/late'))->toBeFalse();

    $manager->register(FixturePackage::make('acme/recovery'));
    expect($manager->has('acme/recovery'))->toBeTrue();
});

it('rejects recursive package boot without repeating hooks', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('acme/first', onBoot: fn () => $manager->boot(['acme/first'], packageContext())));

    expect(fn () => $manager->boot(['acme/first'], packageContext()))->toThrow(BootstrapException::class, 'Cannot boot GCEGO packages recursively.');
});

it('inspects direct and transitive dependencies and dependants including optional edges on demand', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('acme/base'));
    $manager->register(FixturePackage::make('acme/middle', requires: ['acme/base']));
    $manager->register(FixturePackage::make('acme/top', requires: ['acme/middle'], optional: ['acme/optional']));
    $manager->register(FixturePackage::make('acme/optional'));

    expect($manager->dependencies('acme/top'))->toBe(['acme/middle'])
        ->and($manager->dependencies('acme/top', true))->toBe(['acme/base', 'acme/middle'])
        ->and($manager->dependencies('acme/top', true, true))->toBe(['acme/base', 'acme/middle', 'acme/optional'])
        ->and($manager->dependants('acme/base'))->toBe(['acme/middle'])
        ->and($manager->dependants('acme/base', true))->toBe(['acme/middle', 'acme/top'])
        ->and($manager->dependants('acme/optional', true, true))->toBe(['acme/top'])
        ->and(fn () => $manager->dependencies('acme/unknown'))->toThrow(PackageRegistrationException::class);
});

it('inspects invalid dependency graphs without looping or dropping missing requirements', function (): void {
    $manager = new PackageManager;
    $manager->register(FixturePackage::make('acme/a', requires: ['acme/b']));
    $manager->register(FixturePackage::make('acme/b', requires: ['acme/a', 'acme/missing']));

    expect($manager->dependencies('acme/a', true))->toBe(['acme/b', 'acme/missing'])
        ->and($manager->dependants('acme/a', true))->toBe(['acme/b']);
});
