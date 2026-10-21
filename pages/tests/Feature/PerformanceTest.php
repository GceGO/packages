<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Section;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Registry\PageKind;
use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;

it('registers routes for a large registry quickly, from descriptors only, without queries', function (): void {
    $registry = app(Registry::class);

    for ($i = 0; $i < 300; $i++) {
        $registry->register(new Page(new Property(Page::Name, 'bulk.page'.$i), new Property(Page::Title, 'Bulk '.$i), new Authenticated, new Content('Page '.$i)));
    }

    $count = count(app(PageDefinitions::class)->descriptors());
    $router = new Router(new Dispatcher, new Container);
    $started = hrtime(true);
    $routes = [];
    $queries = queriesDuring(function () use ($router, &$routes): void {
        $routes = app(PageRouteRegistrar::class)->register($router);
    });
    $milliseconds = (hrtime(true) - $started) / 1e6;
    recordPerformance(['route_registration_pages' => $count, 'route_registration_ms' => round($milliseconds, 1)]);

    expect($routes)->toHaveCount($count)
        ->and($count)->toBe(311)
        ->and($queries)->toBe([])
        ->and($milliseconds)->toBeLessThan(1000);
});

it('opens and renders record pages with a small, fixed number of queries', function (): void {
    $world = world();
    $view = queriesDuring(fn () => asMember($world['admin'])->get('/admin/subjects/1')->assertOk());
    $edit = queriesDuring(fn () => asMember($world['admin'])->get('/admin/subjects/1/edit')->assertOk());
    $index = queriesDuring(fn () => asMember($world['admin'])->get('/admin/subjects')->assertOk());
    $dashboard = queriesDuring(fn () => asMember($world['admin'])->get('/admin')->assertOk());
    $subjectQueries = static fn (array $queries): int => count(array_filter($queries, static fn (string $sql): bool => str_contains($sql, '"subjects"')));
    recordPerformance([
        'queries_view_page' => count($view),
        'queries_view_page_subjects' => $subjectQueries($view),
        'queries_edit_page' => count($edit),
        'queries_index_page' => count($index),
        'queries_dashboard_page' => count($dashboard),
    ]);

    expect(count($view))->toBeLessThanOrEqual(12)
        ->and(count($edit))->toBeLessThanOrEqual(14)
        ->and(count($index))->toBeLessThanOrEqual(12)
        ->and(count($dashboard))->toBeLessThanOrEqual(10);
});

it('keeps the page host payload small', function (): void {
    $world = world();
    $html = (string) asMember($world['admin'])->get('/admin/subjects/1')->assertOk()->getContent();
    $snapshot = snapshotOf($html, 'gcego-page');
    recordPerformance(['page_host_snapshot_bytes' => strlen($snapshot), 'view_page_html_bytes' => strlen($html)]);

    expect(strlen($snapshot))->toBeLessThan(600);
});

it('renders large nested pages in reasonable time', function (): void {
    $world = world();
    $sections = [];

    for ($i = 0; $i < 60; $i++) {
        $sections[] = new Section('Section '.$i, new Section('Inner '.$i, new Content('Paragraph '.$i)));
    }

    app(Registry::class)->register(new Page('large', 'Large', new Authenticated, ...$sections));
    actAs($world['admin']);
    pages()->render('large');
    app()->forgetScopedInstances();
    actAs($world['admin']);

    $started = hrtime(true);
    $html = pages()->render('large')->toHtml();
    $milliseconds = (hrtime(true) - $started) / 1e6;
    recordPerformance(['render_large_page_components' => 181, 'render_large_page_ms' => round($milliseconds, 1), 'render_large_page_bytes' => strlen($html)]);

    expect(substr_count($html, 'Paragraph '))->toBe(60)
        ->and($milliseconds)->toBeLessThan(2000);
});

it('boots without loading pages beyond the registry index or querying', function (): void {
    $config = app(PagesConfig::class);

    expect($config->routing)->toBeTrue()
        ->and(app(Registry::class)->count(PageKind::Id))->toBe(11)
        ->and(queriesDuring(static fn () => app(PageRouteRegistrar::class)->register(new Router(new Dispatcher, new Container))))->toBe([]);
});
