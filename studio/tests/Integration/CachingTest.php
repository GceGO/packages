<?php

declare(strict_types=1);

use Gcego\Panels\PanelsConfig;
use Gcego\Studio\Tests\TestCase;
use Illuminate\Routing\Route;

afterEach(function (): void {
    @unlink(app()->getCachedRoutesPath());
    @unlink(app()->getCachedConfigPath());
});

/**
 * Writes the route cache exactly as `php artisan route:cache` does (prepared, compiled, var_export'ed).
 */
function cacheRoutes(): void
{
    $routes = app('router')->getRoutes();

    foreach ($routes as $route) {
        assert($route instanceof Route);
        $route->prepareForSerialization();
    }

    $stub = (string) file_get_contents(base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/routes.stub'));
    file_put_contents(app()->getCachedRoutesPath(), str_replace('{{routes}}', var_export($routes->compile(), true), $stub));
}

/**
 * Writes the configuration cache exactly as `php artisan config:cache` does.
 */
function cacheConfiguration(): void
{
    file_put_contents(app()->getCachedConfigPath(), '<?php return '.var_export(config()->all(), true).';'.PHP_EOL);
}

it('works with cached routes: panel routes come from the cache and stay protected', function (): void {
    cacheRoutes();
    $this->refreshApplication();
    require app()->getCachedRoutesPath();   // what the application's RouteServiceProvider does when routes are cached

    expect(app()->routesAreCached())->toBeTrue()
        ->and(app('router')->getRoutes()->getByName('gcego.admin.academics.subjects.index')?->uri())->toBe('admin/subjects')
        ->and(app('router')->getRoutes()->getByName('gcego.admin.academics.subjects.index')?->gatherMiddleware())->toContain('auth')->toContain('gcego.panel:admin');

    $this->get('/admin/subjects')->assertRedirect('/login');
    expect(studio()->health()->result('routes.panels')?->status->value)->toBe('pass')
        ->and(studio()->health(true)->result('production.route-cache')?->status->value)->toBe('pass');
});

it('works with cached configuration', function (): void {
    $this->reboot(['gcego-studio.appearance.default_layout' => 'compact']);
    cacheConfiguration();
    TestCase::$overrides = [];
    $this->refreshApplication();

    expect(app()->configurationIsCached())->toBeTrue()
        ->and(studio()->configuration()->defaultLayout)->toBe('compact')
        ->and(app(PanelsConfig::class)->defaultLayout)->toBe('compact')
        ->and(studio()->panels())->toBe(['admin', 'school'])
        ->and(studio()->health(true)->result('production.config-cache')?->status->value)->toBe('pass');
});
