<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\Authenticated;

it('explains a healthy panel', function (): void {
    $report = panels()->inspect('admin');

    expect($report->healthy())->toBeTrue()
        ->and($report->diagnostics)->toBe([])
        ->and($report->path)->toBe('/admin')
        ->and($report->routeNamespace)->toBe('gcego.admin.')
        ->and($report->guard)->toBe('web')
        ->and($report->navigation)->toBe('navigation:panel-admin')
        ->and($report->pages)->toContain('page:academics-subjects', 'page:overview', 'page:reports')
        ->and($report->routes['page:reports'])->toBe('gcego.admin.reports')
        ->and($report->middleware)->toBe(['web', 'auth', 'gcego.tenant:optional', 'gcego.panel:admin'])
        ->and(panels()->inspect('school')->path)->toBe('/school/{institution}');
});

it('reports panels nobody may enter', function (): void {
    expect(panels()->inspect('vault')->codes())->toBe(['access.undeclared'])
        ->and(panels()->inspect('vault')->healthy())->toBeFalse();
});

it('reports broken references and configuration', function (): void {
    config()->set('auth.guards', ['web' => ['driver' => 'session', 'provider' => 'users']]);
    panels()->register(new Panel(
        new Property(Panel::Name, 'broken'),
        new Property(Panel::Guard, 'missing'),
        new Property(Panel::Navigation, 'nowhere'),
        new Property(Panel::Theme, 'neon'),
        new Authenticated,
        new ModuleReference('finance'),
        new PageReference('absent'),
    ));
    $report = panels()->inspect('broken');

    expect($report->codes())->toBe(['module.missing', 'page.missing', 'guard.unknown', 'navigation.missing', 'theme.unknown'])
        ->and($report->healthy())->toBeFalse();
});

it('reports panels whose routes are missing', function (): void {
    panels()->register(new Panel('unrouted', new Authenticated, new PageReference('reports')));

    expect(panels()->inspect('unrouted')->codes())->toBe(['routes.missing']);
});

it('inspects every panel without user data or queries', function (): void {
    $world = world();
    actAs($world['admin']);
    $queries = queriesDuring(static function () use (&$reports): void {
        $reports = panels()->inspectAll();
    });

    expect(array_keys($reports))->toBe(['panel:vault', 'panel:admin', 'panel:school', 'panel:staff'])
        ->and($queries)->toBe([])
        ->and(json_encode(array_map(static fn ($report): array => $report->toArray(), $reports)))->not->toContain('Admin"');
});

it('builds panel URLs', function (): void {
    expect(panels()->url('admin'))->toBe('/admin')
        ->and(panels()->url('school', ['institution' => 2]))->toBe('/school/2')
        ->and(panels()->pageUrl('admin', 'reports'))->toBe('/admin/reports')
        ->and(panels()->pageUrl('admin', 'academics.subjects.view', 3))->toBe('/admin/subjects/3')
        ->and(panels()->pageUrl('admin', 'page:academics-subjects-view', 3))->toBe('/admin/subjects/3')
        ->and(panels()->pageUrl('admin', 'missing'))->toBeNull()
        ->and(panels()->pageUrl('staff', 'page:academics-subjects'))->toBeNull()
        ->and(panels()->current())->toBeNull()
        ->and(panels()->switcher())->toBe([]);
});
