<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Pages\Exceptions\PageRouteConflictException;
use Gcego\Pages\Http\PageController;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Registry\PageKind;
use Gcego\Pages\Routing\PageRouteDefinition;
use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Pages\Tests\Fixtures\Definitions;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Identity\DefinitionIdentity;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Index\SourceType;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A registry that serves descriptors but refuses to load any definition.
 *
 * @param  list<DefinitionDescriptor>  $descriptors
 */
function indexOnlyRegistry(array $descriptors): RegistryInterface
{
    return new class($descriptors) implements RegistryInterface
    {
        /**
         * @param  list<DefinitionDescriptor>  $descriptors
         */
        public function __construct(private array $descriptors) {}

        public function has(string $identifier): bool
        {
            return true;
        }

        public function descriptor(string $identifier): DefinitionDescriptor
        {
            throw new LogicException('not needed');
        }

        public function get(string $identifier, ?string $expected = null): object
        {
            throw new LogicException('Route registration loaded the definition '.$identifier);
        }

        public function descriptors(?string $kind = null, ?string $scope = null, ?SourceType $sourceType = null, ?string $location = null): array
        {
            return $this->descriptors;
        }

        public function names(?string $kind = null): array
        {
            return [];
        }

        public function count(?string $kind = null): int
        {
            return count($this->descriptors);
        }

        public function index(): DefinitionIndex
        {
            return new DefinitionIndex(...$this->descriptors);
        }

        public function register(object $definition, ?string $scope = null): DefinitionDescriptor
        {
            throw new LogicException('not needed');
        }

        public function refresh(): void {}
    };
}

function descriptorOf(Page $page): DefinitionDescriptor
{
    $kind = new PageKind;

    return new DefinitionDescriptor(new DefinitionIdentity('page', $kind->name($page)), DefinitionSource::runtime($page::class), $page::class, $kind->metadata($page));
}

function freshRouter(): Router
{
    return new Router(new Dispatcher, new Container);
}

it('registers one named GET route per page when the application boots', function (): void {
    $routes = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $identity = PageRouteResolver::pageOf($route);

        if ($identity !== null) {
            $routes[$identity] = [$route->getName(), $route->uri(), $route->methods(), $route->middleware(), $route->wheres];
        }
    }

    expect($routes)->toHaveCount(count(Definitions::all()))
        ->and($routes['page:dashboard'])->toBe(['gcego.dashboard', 'admin', ['GET', 'HEAD'], ['web', 'auth', 'gcego.tenant'], []])
        ->and($routes['page:subjects'])->toBe(['gcego.subjects', 'admin/subjects', ['GET', 'HEAD'], ['web', 'auth', 'gcego.tenant'], []])
        ->and($routes['page:subjects-create'][0])->toBe('gcego.subjects.create')
        ->and($routes['page:subjects-edit'])->toBe(['gcego.subjects.edit', 'admin/subjects/{record}/edit', ['GET', 'HEAD'], ['web', 'auth', 'gcego.tenant'], ['record' => '[A-Za-z0-9][A-Za-z0-9_-]*']])
        ->and($routes['page:catalogue-entry'][4])->toBe(['record' => '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}'])
        ->and($routes['page:institutions-members'])->toBe(['gcego.institutions.members', 'admin/institutions/{institution}/members', ['GET', 'HEAD'], ['web', 'auth', 'gcego.tenant', 'gcego.tenant:required'], ['institution' => '[0-9]+']])
        ->and(app('router')->getRoutes()->getByName('gcego.subjects.view')?->getActionName())->toBe(PageController::class);
});

it('builds page URLs from their routes', function (): void {
    expect(pages()->url('subjects'))->toBe('/admin/subjects')
        ->and(pages()->url('subjects.edit', 7))->toBe('/admin/subjects/7/edit')
        ->and(pages()->url('page:subjects-view', 'abc'))->toBe('/admin/subjects/abc')
        ->and(pages()->url('institutions.members', null, ['institution' => 1]))->toBe('/admin/institutions/1/members')
        ->and(pages()->url('institutions.members'))->toBeNull()
        ->and(pages()->url('dashboard'))->toBe('/admin');
});

it('stores everything routing needs in the registry index', function (): void {
    $descriptor = app(PageDefinitions::class)->descriptor('institutions.members');
    $definition = PageRouteDefinition::fromDescriptor($descriptor);

    expect($descriptor->metadata)->toMatchArray(['name' => 'institutions.members', 'type' => 'custom', 'title' => 'Members', 'path' => 'institutions/{institution}/members', 'route' => 'institutions.members', 'navigation' => true])
        ->and($definition)->toEqual(PageRouteDefinition::fromPage(Definitions::members()))
        ->and($definition->parameters())->toBe(['institution']);
});

it('registers routes from the index alone, without loading a page', function (): void {
    $registry = indexOnlyRegistry(array_map(descriptorOf(...), Definitions::all()));
    $registrar = new PageRouteRegistrar(new PageDefinitions($registry), app(PagesConfig::class));
    $routes = $registrar->register(freshRouter());

    expect($routes)->toHaveCount(count(Definitions::all()))
        ->and(array_map(static fn (Route $route): string => $route->uri(), $routes))->toContain('admin/subjects/{record}/edit', 'admin/catalogue/{record}');
});

it('lets panels register pages under their own prefix, names, middleware and domain', function (): void {
    $router = freshRouter();
    $routes = app(PageRouteRegistrar::class)->register($router, prefix: 'school', namePrefix: 'school.', middleware: ['web'], domain: 'school.test', filter: static fn (PageRouteDefinition $route): bool => str_starts_with($route->page, 'subjects'));

    expect(array_map(static fn (Route $route): array => [$route->getName(), $route->uri(), $route->middleware(), $route->getDomain()], $routes))->toBe([
        ['school.subjects', 'school/subjects', ['web'], 'school.test'],
        ['school.subjects.create', 'school/subjects/create', ['web'], 'school.test'],
        ['school.subjects.edit', 'school/subjects/{record}/edit', ['web'], 'school.test'],
        ['school.subjects.view', 'school/subjects/{record}', ['web'], 'school.test'],
    ]);
});

it('refuses route names and URLs that are already taken', function (Closure $existing, string $message): void {
    $router = freshRouter();
    $existing($router);

    expect(fn () => app(PageRouteRegistrar::class)->register($router))->toThrow(PageRouteConflictException::class, $message);
})->with([
    'name' => [static fn (Router $router) => $router->get('/elsewhere', static fn (): string => '')->name('gcego.subjects'), 'route name "gcego.subjects": it is already used by route "gcego.subjects"'],
    'URL' => [static fn (Router $router) => $router->get('/admin/subjects/{subject}', static fn (): string => ''), 'GET "admin/subjects/{record}": the same URL is already answered by route "admin/subjects/{subject}"'],
]);

it('refuses two pages with the same URL', function (): void {
    $twin = new Page(new Property(Page::Name, 'twin'), new Property(Page::Path, 'subjects/create'), new Authenticated);
    $registry = indexOnlyRegistry([descriptorOf(Definitions::createSubject()), descriptorOf($twin)]);

    expect(fn () => (new PageRouteRegistrar(new PageDefinitions($registry), app(PagesConfig::class)))->register(freshRouter()))
        ->toThrow(PageRouteConflictException::class, 'already answered by page page:subjects-create');
});

it('works with cached (compiled) routes', function (): void {
    $collection = app('router')->getRoutes();

    foreach ($collection->getRoutes() as $route) {
        if (PageRouteResolver::pageOf($route) !== null) {
            $route->prepareForSerialization();
        }
    }

    $compiled = (new CompiledRouteCollection(...array_values(serializable(app('router')))))->setRouter(app('router'))->setContainer(app());
    $route = $compiled->match(Request::create('/admin/subjects/12/edit'));

    expect(PageRouteResolver::pageOf($route))->toBe('page:subjects-edit')
        ->and($route->getName())->toBe('gcego.subjects.edit')
        ->and($route->parameter('record'))->toBe('12')
        ->and(fn () => $compiled->match(Request::create('/admin/catalogue/not-a-uuid')))->toThrow(NotFoundHttpException::class);
});

/**
 * The compiled page routes, as route:cache writes them.
 *
 * @return array{0: array<mixed>, 1: array<mixed>}
 */
function serializable(Router $router): array
{
    $routes = new RouteCollection;

    foreach ($router->getRoutes()->getRoutes() as $route) {
        if (PageRouteResolver::pageOf($route) !== null) {
            $routes->add($route);
        }
    }

    $compiled = $routes->compile();

    return [unserialize(serialize($compiled['compiled'])), unserialize(serialize($compiled['attributes']))];
}

it('answers 404 for record keys of the wrong shape, before any query', function (): void {
    world();

    expect(queriesDuring(fn () => $this->get('/admin/catalogue/1')->assertNotFound()))->toBe([])
        ->and(queriesDuring(fn () => $this->get('/admin/institutions/abc/members')->assertNotFound()))->toBe([]);
});

it('answers 404 for routes without a page identity', function (): void {
    app('router')->get('/broken', PageController::class);

    $this->actingAs(world()['admin'])->get('/broken')->assertNotFound();
});
