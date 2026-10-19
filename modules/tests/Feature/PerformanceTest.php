<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;

it('compiles and registers a large set of modules quickly, without queries', function (): void {
    $started = hrtime(true);
    $queries = queriesDuring(function (): void {
        for ($module = 0; $module < 40; $module++) {
            $resources = [];

            for ($resource = 0; $resource < 5; $resource++) {
                $resources[] = new Resource(
                    new Property(Resource::Name, 'items'.$resource),
                    new Property(Resource::Slug, 'm'.$module.'-items'.$resource),
                    new Source(Subject::class),
                    new Table(new Text('name', 'Name')),
                    new Form(new TextInput('name', 'Name')),
                    new Create,
                    new Edit,
                    new Infolist(new TextEntry('name', 'Name')),
                );
            }

            modules()->register(new Module('bulk'.$module, ...$resources));
        }
    });
    $registered = (hrtime(true) - $started) / 1e6;

    $router = new Router(new Dispatcher, new Container);
    $started = hrtime(true);
    $routes = app(PageRouteRegistrar::class)->register($router);
    $routing = (hrtime(true) - $started) / 1e6;

    recordPerformance(['modules_registered' => 40, 'resources_registered' => 200, 'pages_registered' => count($routes), 'module_registration_ms' => round($registered, 1), 'route_registration_ms' => round($routing, 1)]);

    expect($queries)->toBe([])
        ->and(count($routes))->toBeGreaterThanOrEqual(800)
        ->and($registered)->toBeLessThan(10000)
        ->and($routing)->toBeLessThan(2000);
});

it('renders generated and relationship pages with a small number of queries', function (): void {
    $world = world();
    $counts = [];

    foreach (['index' => '/admin/subjects', 'view' => '/admin/subjects/1', 'edit' => '/admin/subjects/1/edit', 'relation_index' => '/admin/subjects/1/topics', 'relation_view' => '/admin/subjects/1/topics/1'] as $name => $uri) {
        $started = hrtime(true);
        $counts['queries_'.$name] = count(queriesDuring(fn () => asMember($world['admin'])->get($uri)->assertOk()));
        $counts['render_'.$name.'_ms'] = round((hrtime(true) - $started) / 1e6, 1);
    }

    recordPerformance($counts);

    expect($counts['queries_index'])->toBeLessThanOrEqual(12)
        ->and($counts['queries_relation_index'])->toBeLessThanOrEqual(16)
        ->and($counts['queries_relation_view'])->toBeLessThanOrEqual(18);
});
