<?php

declare(strict_types=1);

use Gcego\Panels\PanelManager;
use Gcego\Panels\PanelsConfig;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Exceptions\StudioConfigurationException;

it('has secure defaults that keep the packages\' own defaults', function (): void {
    $config = app(StudioConfiguration::class);

    expect($config->enabled)->toBeTrue()->and($config->requireAuthentication)->toBeTrue()->and($config->requireAuthorization)->toBeTrue()
        ->and($config->productionDetails)->toBeFalse()->and($config->defaultLayout)->toBeNull()->and($config->vite)->toBeNull()
        ->and($config->extensions)->toBe([])
        ->and(app(PanelsConfig::class)->defaultLayout)->toBe('sidebar');
});

it('refuses invalid configuration with the setting\'s name', function (array $config, string $message): void {
    expect(fn () => StudioConfiguration::fromArray($config))->toThrow(StudioConfigurationException::class, $message);
})->with([
    [['appearance' => ['default_layout' => 'floating']], 'appearance.default_layout'],
    [['appearance' => ['default_theme' => 'Bad Theme!']], 'appearance.default_theme'],
    [['assets' => ['vite' => ['../../secret.js']]], 'assets.vite'],
    [['assets' => ['vite' => 'resources/js/app.js']], 'must be a list'],
    [['security' => ['require_authorization' => 'no']], 'must be true or false'],
    [['extensions' => [stdClass::class]], 'extensions'],
    [['install' => ['panel' => 'Admin Panel']], 'install.panel'],
    [['install' => ['ability' => 'drop table']], 'install.ability'],
]);

it('feeds the panels\' appearance and asset settings only where set; panels keep their own', function (): void {
    $this->reboot(['gcego-studio.appearance.default_layout' => 'compact', 'gcego-studio.appearance.default_theme' => 'midnight', 'gcego-studio.assets.vite' => ['resources/css/admin.css']]);

    $panels = app(PanelsConfig::class);

    expect($panels->defaultLayout)->toBe('compact')->and($panels->defaultTheme)->toBe('midnight')->and($panels->vite)->toBe(['resources/css/admin.css'])
        ->and(app(PanelManager::class)->panel('school')->get('layout'))->toBe('topbar');
});

it('turns discovery of a kind off by removing its registry roots', function (): void {
    $this->reboot(['gcego-studio.discovery.panels' => false, 'gcego-studio.discovery.pages' => false]);

    expect(studio()->panels())->toBe([])
        ->and(studio()->pages())->toBe(['academics-subjects', 'academics-subjects-create', 'academics-subjects-view', 'academics-subjects-edit'])
        ->and(studio()->modules())->toBe(['academics']);

    // The fixture panels reference the academics module, so they go too (gcego/panels refuses dangling references).
    $this->reboot(['gcego-studio.discovery.modules' => false, 'gcego-studio.discovery.panels' => false]);

    expect(studio()->modules())->toBe([])->and(studio()->pages())->toBe(['overview', 'reports']);
});

it('stays inert when disabled', function (): void {
    $this->reboot(['gcego-studio.enabled' => false, 'gcego-studio.appearance.default_layout' => 'topbar']);

    expect(app(PanelsConfig::class)->defaultLayout)->toBe('sidebar')
        ->and(studio()->bootstrap()->problems)->toContain('Studio is disabled (gcego-studio.enabled).');
});
