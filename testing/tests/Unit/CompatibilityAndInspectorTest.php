<?php

declare(strict_types=1);

use Gcego\Actions\ActionsServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Testing\Compatibility\CompatibilityResult;
use Gcego\Testing\Compatibility\PackageCompatibility;
use Gcego\Testing\Diagnostics\TestingDiagnostic;
use Gcego\Testing\Environment\TestApplicationFactory;
use Gcego\Testing\Fixtures\FixtureManager;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\TestingConfig;
use Gcego\Testing\TestingManager;
use Gcego\Testing\TestingServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;

it('reports the installed platform and GCEGO packages', function (): void {
    $results = collect(GcegoTest::compatibility()->check())->keyBy('name');

    expect($results['php']->status)->toBe(CompatibilityResult::Ok)
        ->and($results['laravel/framework']->status)->toBe(CompatibilityResult::Ok)
        ->and($results['phpunit/phpunit']->status)->toBe(CompatibilityResult::Ok)
        ->and($results['gcego/tables']->status)->toBe(CompatibilityResult::Ok)
        ->and($results['gcego/tables']->version)->toBe('0.1.0')
        ->and($results['gcego/sentinel']->status)->toBe(CompatibilityResult::Optional);
});

it('has no compatibility failures in this environment', function (): void {
    expect(collect(GcegoTest::compatibility()->failures())->pluck('name')->all())->toBe([]);
});

it('flags an installed package whose provider is not registered', function (): void {
    $app = new Application(sys_get_temp_dir());
    $results = collect((new PackageCompatibility($app))->check())->keyBy('name');

    expect($results['gcego/tables']->status)->toBe(CompatibilityResult::Incompatible)
        ->and($results['gcego/tables']->message)->toContain('is not registered');
});

it('inspects the test environment without changing it', function (): void {
    $report = GcegoTest::inspector()->inspect();

    expect($report->check('database')?->level)->toBe(TestingDiagnostic::Ok)
        ->and($report->check('isolation.cache')?->level)->toBe(TestingDiagnostic::Ok)
        ->and($report->check('isolation.registry')?->level)->toBe(TestingDiagnostic::Ok)
        ->and($report->check('testing.phpunit')?->level)->toBe(TestingDiagnostic::Ok)
        ->and($report->check('tenancy')?->message)->toContain('enabled')
        ->and($report->isHealthy())->toBeTrue()
        ->and($report->toArray()['packages'])->not->toBe([]);
});

it('warns when the environment can leak state between tests', function (): void {
    $this->reboot(['cache.default' => 'file', 'queue.default' => 'database', 'gcego-registry.cache.enabled' => true]);
    $report = GcegoTest::inspector()->inspect();

    expect($report->check('isolation.cache')?->level)->toBe(TestingDiagnostic::Warning)
        ->and($report->check('isolation.queue')?->level)->toBe(TestingDiagnostic::Warning)
        ->and($report->check('isolation.registry')?->level)->toBe(TestingDiagnostic::Warning);
});

it('lists the installed providers in dependency order', function (): void {
    $providers = TestApplicationFactory::providers();

    expect($providers[0])->toBe(LivewireServiceProvider::class)
        ->and($providers[1])->toBe(ComponentServiceProvider::class)
        ->and(array_search(SecurityServiceProvider::class, $providers, true))->toBeLessThan(array_search(ActionsServiceProvider::class, $providers, true))
        ->and(end($providers))->toBe(TestingServiceProvider::class)
        ->and(TestApplicationFactory::providers([GcegoServiceProvider::class]))->toBe([GcegoServiceProvider::class, TestingServiceProvider::class]);
});

it('reads its configuration defensively', function (): void {
    $config = TestingConfig::fromArray(['assertions' => ['redact' => ['*pin*', 3]], 'performance' => ['repeated_queries' => 'x'], 'isolation' => ['reset_registry' => false]]);

    expect($config->redact)->toBe(['*pin*'])
        ->and($config->repeatedQueries)->toBe(5)
        ->and($config->isolation)->toBe(['reset_registry' => false, 'reset_runtime' => true, 'reset_tenant_context' => true]);
});

it('registers nothing when disabled', function (): void {
    $app = new Application(sys_get_temp_dir());
    $app->instance('config', new Repository(['gcego-testing' => ['enabled' => false]]));
    (new TestingServiceProvider($app))->register();

    expect($app->bound(TestingManager::class))->toBeFalse()
        ->and($app->bound(FixtureManager::class))->toBeFalse();
});

it('registers its services lazily when enabled', function (): void {
    expect(app()->bound(TestingManager::class))->toBeTrue()
        ->and(app()->resolved(FixtureManager::class))->toBeFalse();
});
