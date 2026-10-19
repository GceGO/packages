<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Panel;
use Gcego\Panels\Routing\PanelRouteRegistrar;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Routing\Router;

it('registers and routes many panels without queries', function (): void {
    $queries = queriesDuring(static function (): void {
        foreach (range(1, 30) as $number) {
            panels()->register(new Panel(new Property(Panel::Name, 'panel-'.$number), new Authenticated, new ModuleReference('academics'), new PageReference('reports')));
        }
    });

    $router = new Router(app('events'), app());
    $start = hrtime(true);
    $routes = app(PanelRouteRegistrar::class)->register($router);
    $milliseconds = (hrtime(true) - $start) / 1e6;
    recordPerformance(['panels.routes.34_panels_ms' => round($milliseconds, 1), 'panels.routes.count' => count($routes)]);

    expect($queries)->toBe([])
        ->and(count($routes))->toBeGreaterThan(200)
        ->and($milliseconds)->toBeLessThan(1500);
});

it('keeps panel requests within a small query budget', function (): void {
    $world = world();
    $count = 0;
    $queries = queriesDuring(static function () use ($world, &$count): void {
        asMember($world['admin'])->get('/admin/subjects')->assertOk();
    });
    recordPerformance(['panels.request.admin_subjects_queries' => count($queries)]);

    // Session user, tenant membership, the subjects page and its count.
    expect(count($queries))->toBeLessThanOrEqual(8);
});
