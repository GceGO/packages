<?php

declare(strict_types=1);

use Gcego\Core\Bootstrap\StartupTelemetry;
use Gcego\Core\Events\ExtensionActivated;
use Gcego\Core\Events\ExtensionActivating;
use Gcego\Core\Events\ExtensionActivationFailed;
use Gcego\Core\Events\FrameworkBooted;
use Gcego\Core\Events\FrameworkBootFailed;
use Gcego\Core\Events\FrameworkBooting;
use Gcego\Core\Events\PackageBooted;
use Gcego\Core\Events\PackageBootFailed;
use Gcego\Core\Events\PackageBooting;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\FixtureExtension;
use Gcego\Core\Tests\Fixtures\FixturePackage;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

it('dispatches typed events in boot order and publishes committed state', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'diagnostics.profiling' => true]);
    $events = [];
    $observations = [];
    foreach ([FrameworkBooting::class, PackageBooting::class, PackageBooted::class, ExtensionActivating::class, ExtensionActivated::class, FrameworkBooted::class] as $type) {
        app('events')->listen($type, function (object $event) use (&$events, &$observations): void {
            $events[] = [$event::class, $event->id ?? null];
            if ($event instanceof PackageBooted) {
                $observations[] = app(Gcego::class)->isPackageActive($event->id);
            }
            if ($event instanceof ExtensionActivated) {
                $observations[] = app(Gcego::class)->isExtensionActive($event->id);
            }
            if ($event instanceof FrameworkBooted) {
                $observations[] = app(Gcego::class)->bootResult() === $event->result;
                $observations[] = app(Gcego::class)->isBooted();
            }
        });
    }
    app(ExtensionManager::class)->register(FixtureExtension::make('acme.export'));
    $gcego = app(Gcego::class);
    $result = $gcego->boot();
    $gcego->boot();

    expect($events)->toBe([
        [FrameworkBooting::class, null],
        [PackageBooting::class, 'gcego/core'],
        [PackageBooted::class, 'gcego/core'],
        [ExtensionActivating::class, 'acme.export'],
        [ExtensionActivated::class, 'acme.export'],
        [FrameworkBooted::class, null],
    ])->and($observations)->toBe([true, true, true, true])
        ->and($result->timings)->toHaveCount(2)
        ->and($result->timings)->toBe($gcego->timings())
        ->and($result->toArray()['timings'][0]['succeeded'])->toBeTrue();

    foreach ($result->timings as $timing) {
        expect($timing->durationMs)->toBeGreaterThanOrEqual(0);
    }
});

it('retains failed package timings across retries without repeating successful work', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'diagnostics.profiling' => true]);
    $attempts = 0;
    $failures = [];
    app('events')->listen(PackageBootFailed::class, function ($event) use (&$failures): void {
        $failures[] = $event;
    });
    app('events')->listen(FrameworkBootFailed::class, function ($event) use (&$failures): void {
        $failures[] = $event;
    });
    app(PackageManager::class)->register(FixturePackage::make('zz/flaky', requires: ['gcego/core'], onBoot: function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('not ready');
        }
    }));
    $gcego = app(Gcego::class);
    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class);
    expect(array_map(fn ($timing) => [$timing->id, $timing->succeeded], $gcego->timings()))->toBe([
        ['gcego/core', true], ['zz/flaky', false],
    ])->and($failures)->toHaveCount(2)
        ->and($failures[0]->exception)->toBeInstanceOf(RuntimeException::class);

    $result = $gcego->boot();
    expect(array_map(fn ($timing) => [$timing->id, $timing->succeeded], $result->timings))->toBe([
        ['gcego/core', true], ['zz/flaky', false], ['zz/flaky', true],
    ])->and($attempts)->toBe(2);
});

it('records extension activation failures and retries', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'diagnostics.profiling' => true]);
    $attempts = 0;
    $failed = null;
    app('events')->listen(ExtensionActivationFailed::class, function ($event) use (&$failed): void {
        $failed = $event;
    });
    app(ExtensionManager::class)->register(FixtureExtension::make('acme.flaky', onActivate: function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('retry');
        }
    }));
    $gcego = app(Gcego::class);
    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class);
    expect($failed?->id)->toBe('acme.flaky');
    expect($gcego->boot()->timings)->toHaveCount(3);
});

it('isolates listener and logger failures without repeating successful hooks or leaking secrets', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'diagnostics.profiling' => true]);
    $logger = new class extends AbstractLogger
    {
        public array $entries = [];

        public function log($level, Stringable|string $message, array $context = []): void
        {
            $this->entries[] = [$message, $context];
            throw new RuntimeException('logger unavailable');
        }
    };
    app()->instance(LoggerInterface::class, $logger);
    $calls = 0;
    app(PackageManager::class)->register(FixturePackage::make('acme/test', onBoot: function () use (&$calls): void {
        $calls++;
    }));
    app('events')->listen(PackageBooted::class, function (): never {
        throw new RuntimeException('password=secret');
    });
    app('events')->listen(FrameworkBooted::class, function (): never {
        throw new RuntimeException('password=secret');
    });
    $gcego = app(Gcego::class);
    $gcego->boot();
    $gcego->boot();

    expect($calls)->toBe(1)
        ->and($gcego->state())->toBe(FrameworkState::Booted)
        ->and($logger->entries)->not->toBeEmpty()
        ->and(json_encode($logger->entries))->not->toContain('password=secret');
});

it('blocks retry from failure observers until the current attempt has unwound', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'diagnostics.profiling' => true]);
    $calls = 0;
    app(PackageManager::class)->register(FixturePackage::make('acme/broken', onBoot: function () use (&$calls): never {
        $calls++;
        throw new RuntimeException('broken');
    }));
    app('events')->listen(FrameworkBootFailed::class, fn () => app(Gcego::class)->boot());
    expect(fn () => app(Gcego::class)->boot())->toThrow(BootstrapException::class)
        ->and($calls)->toBe(1)
        ->and(app(Gcego::class)->state())->toBe(FrameworkState::Failed);
});

it('does not emit startup events or record timings for disabled boots', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'enabled' => false]);
    $events = [];
    app('events')->listen(FrameworkBooting::class, function ($event) use (&$events): void {
        $events[] = $event;
    });
    app(Gcego::class)->boot();
    expect($events)->toBe([])->and(app(StartupTelemetry::class)->timings())->toBe([]);
});

it('seals package registrations before extension activation so new packages cannot miss boot', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'diagnostics.profiling' => true]);
    app(ExtensionManager::class)->register(FixtureExtension::make('acme.late', onActivate: function (): void {
        app(PackageManager::class)->register(FixturePackage::make('acme/late'));
    }));
    $gcego = app(Gcego::class);
    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class, 'registered after package registration was locked')
        ->and($gcego->hasPackage('acme/late'))->toBeFalse()
        ->and(app(PackageManager::class)->isLocked())->toBeTrue()
        ->and(app(ExtensionManager::class)->isLocked())->toBeFalse();
});
