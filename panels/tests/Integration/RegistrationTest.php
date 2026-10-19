<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Exceptions\InvalidPanelException;
use Gcego\Panels\Exceptions\PanelNotFoundException;
use Gcego\Panels\Panel;
use Gcego\Panels\Registry\PanelKind;
use Gcego\Panels\Routing\PanelRouteRegistrar;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Routing\Router;

it('discovers panels from their folder, in switcher order', function (): void {
    expect(panels()->names())->toBe(['vault', 'admin', 'school', 'staff'])
        ->and(app(RegistryInterface::class)->has('panel:admin'))->toBeTrue()
        ->and(panels()->panel('admin'))->toBeInstanceOf(Panel::class)
        ->and(panels()->has('Admin'))->toBeFalse()
        ->and(fn () => panels()->panel('../admin'))->toThrow(PanelNotFoundException::class);
});

it('indexes what routing and switching need as scalar metadata', function (): void {
    $metadata = app(RegistryInterface::class)->descriptor('panel:admin')->metadata;
    $descriptor = panels()->descriptor('admin');

    expect(array_filter($metadata, static fn (mixed $value): bool => ! is_scalar($value) && $value !== null))->toBe([])
        ->and($descriptor->identity)->toBe('panel:admin')
        ->and($descriptor->path)->toBe('admin')
        ->and($descriptor->modules)->toBe(['academics'])
        ->and($descriptor->pages)->toBe(['overview', 'reports'])
        ->and($descriptor->navigation)->toBe('panel-admin')
        ->and($descriptor->declaresAccess)->toBeTrue()
        ->and(panels()->descriptor('vault')->declaresAccess)->toBeFalse();
});

it('registers each panel\'s pages under its prefix and route names', function (): void {
    expect(route('gcego.admin.academics.subjects.index', [], false))->toBe('/admin/subjects')
        ->and(route('gcego.admin.overview', [], false))->toBe('/admin/overview')
        ->and(route('gcego.admin.home', [], false))->toBe('/admin')
        ->and(route('gcego.school.academics.subjects.view', ['institution' => 1, 'record' => 4], false))->toBe('/school/1/subjects/4')
        ->and(route('gcego.staff.home', [], false))->toBe('/staff');
});

it('puts panel middleware in a fixed order: base, auth, tenancy, panel access, then the panel\'s own', function (): void {
    $admin = app('router')->getRoutes()->getByName('gcego.admin.academics.subjects.index');
    $school = app('router')->getRoutes()->getByName('gcego.school.home');

    expect($admin?->gatherMiddleware())->toBe(['web', 'auth', 'gcego.tenant:optional', 'gcego.panel:admin'])
        ->and($school?->gatherMiddleware())->toBe(['web', 'auth', 'gcego.tenant:required', 'gcego.panel:school']);
});

it('routes a page only in the panels that include it', function (): void {
    $resolver = app(PageRouteResolver::class);

    expect($resolver->routeNames('page:academics-subjects'))->toBe(['gcego.admin.academics.subjects.index', 'gcego.school.academics.subjects.index'])
        ->and($resolver->routeNames('page:reports'))->toBe(['gcego.admin.reports', 'gcego.staff.reports'])
        ->and($resolver->routeNames('page:overview'))->toBe(['gcego.admin.overview'])
        ->and($resolver->routeName('page:help'))->toBeNull()
        ->and(app('router')->getRoutes()->getByName('gcego.academics.subjects.index'))->toBeNull();
});

it('keeps links in the current panel when a page is in several', function (): void {
    onPage('/school/1/subjects');

    expect(app(PageRouteResolver::class)->routeName('page:academics-subjects-create'))->toBe('gcego.school.academics.subjects.create');

    onPage('/admin/subjects');

    expect(app(PageRouteResolver::class)->routeName('page:academics-subjects-create'))->toBe('gcego.admin.academics.subjects.create');
});

it('routes panels registered at runtime, with a domain, guard and verification', function (): void {
    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    routePanel(new Panel(
        new Property(Panel::Name, 'partners'),
        new Property(Panel::Domain, 'partners.example.com'),
        new Property(Panel::Path, ''),
        new Property(Panel::Guard, 'staff'),
        new Property(Panel::Verified, true),
        new Property(Panel::Middleware, ['throttle:60,1']),
        new Authenticated,
        new PageReference('reports'),
    ));
    $route = app('router')->getRoutes()->getByName('gcego.partners.reports');

    expect($route?->getDomain())->toBe('partners.example.com')
        ->and($route?->uri())->toBe('reports')
        ->and($route?->gatherMiddleware())->toBe(['web', 'auth:staff', 'verified', 'gcego.panel:partners', 'throttle:60,1'])
        ->and(app('router')->getRoutes()->getByName('gcego.partners.home')?->uri())->toBe('/');
});

it('refuses two panels on the same path and domain', function (): void {
    panels()->register(new Panel('duplicate', new Property(Panel::Path, 'admin'), new Authenticated));

    expect(fn () => app(PanelRouteRegistrar::class)->register(new Router(app('events'), app())))
        ->toThrow(InvalidPanelException::class, 'already uses the path "/admin"');
});

it('refuses two panels with the same route names', function (): void {
    panels()->register(new Panel('duplicate', new Property(Panel::RouteName, 'admin'), new Authenticated));

    expect(fn () => app(PanelRouteRegistrar::class)->register(new Router(app('events'), app())))
        ->toThrow(InvalidPanelException::class, 'already uses the route names "gcego.admin.*"');
});

it('refuses references to modules and pages that are not registered', function (Panel $panel, string $message): void {
    panels()->register($panel);

    expect(fn () => app(PanelRouteRegistrar::class)->registerPanel(new Router(app('events'), app()), panels()->descriptor($panel->panelName())))
        ->toThrow(InvalidPanelException::class, $message);
})->with([
    [fn () => new Panel('broken', new Authenticated, new ModuleReference('finance')), 'module "finance", which is not registered'],
    [fn () => new Panel('broken', new Authenticated, new PageReference('missing')), 'page "missing", which is not registered'],
]);

it('leaves page routing to gcego/pages when panels do not route pages', function (): void {
    $this->reboot(['gcego-panels.routing.register_page_routes' => false]);

    expect(app('router')->getRoutes()->getByName('gcego.admin.home'))->toBeNull()
        ->and(app(PageRouteResolver::class)->routeName('page:help'))->toBe('gcego.help')
        ->and(config('gcego-pages.rendering.layout'))->toBe('gcego-pages::layouts.app');
});

it('registers the panel kind', function (): void {
    expect((new PanelKind)->id())->toBe('panel')
        ->and((new PanelKind)->validate(new Panel('Bad Name')))->not->toBe([]);
});
