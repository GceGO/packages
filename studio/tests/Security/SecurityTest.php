<?php

declare(strict_types=1);

use Gcego\Studio\Bootstrap\StudioBootstrapper;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Tests\Fixtures\Extensions\ReportsExtension;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

it('has no configuration that loosens authentication or authorization', function (): void {
    $this->reboot(['gcego-studio.security.require_authentication' => false, 'gcego-studio.security.require_authorization' => false]);
    $world = world();

    expect(app(StudioConfiguration::class)->requireAuthorization)->toBeFalse();

    $this->get('/admin/subjects')->assertRedirect('/login');
    asMember($world['reader'])->get('/admin/subjects')->assertForbidden();
    expect(studio()->health()->result('security.authentication')?->status->value)->toBe('skipped');
});

it('never grants access: an undeclared panel ability denies everyone', function (): void {
    $world = world();
    Gate::define('access-admin', static fn (): bool => false);

    foreach (['admin', 'editor', 'reader'] as $who) {
        asMember($world[$who])->get('/admin/subjects')->assertForbidden();
    }
});

it('routes extension panel routes behind the panel\'s authentication and authorization', function (): void {
    $this->reboot(['gcego-studio.extensions' => [ReportsExtension::class]]);
    $world = world();

    $this->get('/admin/acme-reports')->assertRedirect('/login');
    asMember($world['reader'])->get('/admin/acme-reports')->assertForbidden();
    asMember($world['editor'])->get('/admin/acme-reports')->assertOk()->assertSee('Acme reports');

    expect(html(asMember($world['editor'])->get('/admin/overview')))->toContain('data-acme-reports');
});

it('keeps paths, middleware and details out of the inspector in production', function (): void {
    $this->reboot(['app.env' => 'production']);
    $report = (string) json_encode(studio()->inspect());

    expect($report)->not->toContain(base_path())->not->toContain('"middleware"')->not->toContain(dirname(__DIR__))
        ->and(studio()->inspect()['panels'][0]['identity'] ?? null)->toBe('panel:admin');

    $this->reboot(['app.env' => 'production', 'gcego-studio.diagnostics.production_details' => true]);
    expect((string) json_encode(studio()->inspect()))->toContain('"middleware"')->not->toContain(base_path());
});

it('exposes no web route of its own', function (): void {
    $studioRoutes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_contains((string) $route->getActionName(), 'Gcego\\Studio'));

    expect($studioRoutes)->toBeEmpty();
});

it('runs no query while the framework boots', function (): void {
    $queries = [];
    $this->beforeApplicationDestroyed(static fn () => null);
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(StudioBootstrapper::class)->verify(true);
    studio()->panels();
    studio()->capabilities();

    expect($queries)->toBe([]);
});
