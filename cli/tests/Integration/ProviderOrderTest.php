<?php

declare(strict_types=1);

use Gcego\Cli\CliServiceProvider;
use Gcego\Registry\Kinds\DefinitionKinds;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

/**
 * Laravel's package discovery registers providers in vendor/composer/installed.json
 * order (alphabetical), not dependency order: every package must work that way.
 */
it('boots with providers in Composer\'s package-discovery order', function (): void {
    $installed = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/vendor/composer/installed.json'), true);
    $providers = [LivewireServiceProvider::class];

    foreach ($installed['packages'] as $package) {
        foreach ($package['extra']['laravel']['providers'] ?? [] as $provider) {
            if (str_starts_with($provider, 'Gcego\\')) {
                $providers[] = $provider;
            }
        }
    }

    $providers[] = CliServiceProvider::class;
    expect(array_search('Gcego\\Pages\\PagesServiceProvider', $providers, true))->toBeLessThan(array_search('Gcego\\Registry\\RegistryServiceProvider', $providers, true));

    $app = Application::create(null, null, ['extra' => ['providers' => $providers, 'dont-discover' => ['*']]]);

    expect($app->make(DefinitionKinds::class)->ids())->toEqualCanonicalizing(['blueprint', 'widget', 'layout', 'navigation', 'page', 'module', 'panel', 'workflow', 'settings', 'settings-page', 'dataset', 'metric', 'report', 'analytics-dashboard']);
});
