<?php

declare(strict_types=1);

use Gcego\Notifications\NotificationsServiceProvider;
use Gcego\Studio\Exceptions\IncompatiblePackageException;
use Gcego\Studio\Integration\PackageIntegrationManager;
use Gcego\Widgets\WidgetsServiceProvider;

it('verifies the eighteen foundation packages are installed, discovered, registered and booted', function (): void {
    $statuses = collect(studio()->packages())->keyBy('id');

    expect($statuses->where('required', true))->toHaveCount(18)
        ->and($statuses->where('required', true)->every(fn ($status) => $status->integrated()))->toBeTrue()
        ->and($statuses['gcego/morfaw']->provider)->toBeNull()
        ->and($statuses['gcego/panels']->version)->toBe('0.1.0')
        ->and($statuses['gcego/notifications']->integrated())->toBeTrue()
        ->and($statuses['gcego/workflows']->installed)->toBeFalse()
        ->and(studio()->bootstrap()->ok)->toBeTrue()
        ->and(studio()->bootstrap()->kernel)->toBe('booted');
});

it('detects optional packages as capabilities without depending on them', function (): void {
    expect(studio()->hasCapability('notifications'))->toBeTrue()->and(studio()->hasCapability('workflows'))->toBeFalse()
        ->and(app(PackageIntegrationManager::class)->has('gcego/notifications'))->toBeTrue()
        ->and(app(PackageIntegrationManager::class)->has('gcego/analytics'))->toBeFalse();

    $this->reboot([], [NotificationsServiceProvider::class]);

    expect(studio()->hasCapability('notifications'))->toBeFalse()->and(studio()->bootstrap()->ok)->toBeTrue();
});

it('reports a missing foundation package with the fix, instead of failing silently', function (): void {
    $this->reboot(['gcego.auto_boot' => false], [WidgetsServiceProvider::class]);

    $result = studio()->bootstrap();

    expect($result->ok)->toBeFalse()->and($result->kernel)->toBe('failed')
        ->and(implode("\n", $result->problems))->toContain('gcego/widgets is installed but its service provider Gcego\Widgets\WidgetsServiceProvider is not loaded')
        ->and(implode("\n", $result->problems))->toContain('requires "gcego/widgets"')
        ->and(fn () => studio()->manager()->bootstrapper()->assertReady())->toThrow(IncompatiblePackageException::class)
        ->and(studio()->health()->result('packages.integrated')?->status->value)->toBe('failure');
});

it('checks platform and package constraints against what is really installed', function (): void {
    $report = studio()->compatibility();

    expect($report->compatible())->toBeTrue()->and($report->versions)->toHaveKeys(['php', 'laravel/framework', 'livewire/livewire', 'gcego/core', 'gcego/panels'])
        ->and($report->notices)->toBe([]);
});
