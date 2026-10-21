<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Panels\Panel;
use Gcego\Panels\PanelManager;
use Gcego\Studio\Health\HealthStatus;
use Gcego\Studio\Tests\Fixtures\Extensions\ReportsExtension;
use Gcego\Studio\Tests\Fixtures\Extensions\ReportsHealthCheck;
use Illuminate\Support\Facades\Route;

function status(string $check, bool $production = false): string
{
    return studio()->health($production)->result($check)?->status->value ?? 'missing';
}

function assetsReady(): void
{
    workspaceFile('resources/css/app.css', "@import \"tailwindcss\";\n@import \"../../vendor/gcego/chroma/resources/css/gcego.css\";\n");
    workspaceFile('resources/js/app.js', "import './bootstrap';\n");
    workspaceFile('public/build/assets/app-abc.css', 'body{}');
    workspaceFile('public/build/manifest.json', (string) json_encode(['resources/css/app.css' => ['file' => 'assets/app-abc.css', 'isEntry' => true]]));
}

it('passes on a correctly assembled application', function (): void {
    $this->reboot(['gcego-studio.assets.vite' => ['resources/css/app.css']]);
    assetsReady();
    $report = studio()->health();

    expect($report->healthy())->toBeTrue()
        ->and(collect($report->results)->pluck('status')->map(fn (HealthStatus $status) => $status->value)->unique()->values()->all())->toBe(['pass'])
        ->and(collect($report->results)->pluck('id')->all())->toBe(['platform.compatibility', 'packages.integrated', 'core.boot', 'configuration.valid', 'registry.index', 'routes.panels', 'security.authentication', 'panels.access', 'security.tenancy', 'assets.integration', 'storage.writable']);

    $this->artisan('gcego:studio:health')->expectsOutputToContain('HEALTHY')->assertSuccessful();
});

it('checks the Vite manifest, Tailwind CSS v4, gcego/chroma and Alpine without building anything', function (): void {
    $this->reboot(['gcego-studio.assets.vite' => ['resources/css/app.css', 'resources/js/admin.js']]);
    assetsReady();
    $assets = studio()->manager()->assets()->inspect();

    expect($assets->missingEntries)->toBe(['resources/js/admin.js'])->and(status('assets.integration'))->toBe('failure');

    workspaceFile('public/build/manifest.json', (string) json_encode(['resources/css/app.css' => ['file' => 'assets/gone.css'], 'resources/js/admin.js' => ['file' => 'assets/app-abc.css']]));
    expect(studio()->manager()->assets()->inspect()->missingFiles)->toBe(['assets/gone.css']);

    $this->reboot([]);
    workspaceFile('resources/css/app.css', "@tailwind base;\n");
    workspaceFile('resources/js/app.js', "import Alpine from 'alpinejs';\nAlpine.start();\n");
    $assets = studio()->manager()->assets()->inspect();

    expect($assets->tailwind4)->toBeFalse()->and($assets->stylingImported)->toBeFalse()->and($assets->duplicateAlpine)->toBeTrue()
        ->and(status('assets.integration'))->toBe('warning')
        ->and(studio()->health()->result('assets.integration')?->message)->toContain('Livewire already ships and starts it');
});

it('treats the Vite dev server as available assets in development, but not in production', function (): void {
    $this->reboot(['gcego-studio.assets.vite' => ['resources/css/app.css']]);
    $hot = public_path('hot');
    file_put_contents($hot, 'http://localhost:5173');

    try {
        expect(studio()->manager()->assets()->inspect()->problems)->toBe([])
            ->and(status('production.assets', true))->toBe('failure');
    } finally {
        @unlink($hot);
    }
});

it('fails panels that nobody may enter and surfaces package inspector errors', function (): void {
    app(PanelManager::class)->register(new Panel(new Property(Panel::Name, 'vault'), new Property(Panel::Path, 'vault')));

    expect(status('panels.access'))->toBe('failure')
        ->and(studio()->health()->result('panels.access')?->message)->toContain('panel:vault declare no access rule');
});

it('fails route collisions between panels and with application routes', function (): void {
    Route::get('/admin', static fn (): string => 'shadow');
    app('router')->getRoutes()->refreshNameLookups();

    expect(status('routes.panels'))->toBe('failure')
        ->and(studio()->health()->result('routes.panels')?->message)->toContain('collides with panel "admin"');
});

it('fails tenant panels without tenancy, and warns when tenancy scopes nothing', function (): void {
    $this->reboot(['gcego-security.tenancy' => ['enabled' => false]]);
    expect(status('security.tenancy'))->toBe('failure');

    $this->reboot(['gcego-security.tenancy.models' => []]);
    expect(status('security.tenancy'))->toBe('warning');
});

it('warns without a login route and fails unknown guards', function (): void {
    $this->reboot(['auth.defaults.guard' => 'staff']);

    expect(status('security.authentication'))->toBe('failure')
        ->and(studio()->health()->result('security.authentication')?->message)->toContain('the guard "staff" is not defined');
});

it('runs extension checks in isolation: an exception becomes a failed check', function (): void {
    $this->reboot(['gcego-studio.extensions' => [ReportsExtension::class]]);

    expect(status('acme.reports'))->toBe('pass');

    ReportsHealthCheck::$explode = true;

    try {
        $result = studio()->health()->result('acme.reports');
        expect($result?->status)->toBe(HealthStatus::Failure)->and($result?->message)->toContain('The reports service is unreachable.')
            ->and(studio()->health()->result('storage.writable')?->status)->toBe(HealthStatus::Pass);
    } finally {
        ReportsHealthCheck::$explode = false;
    }
});

it('adds production readiness checks on request', function (): void {
    $this->reboot(['app.debug' => true, 'app.url' => 'http://example.test', 'session.driver' => 'array', 'session.same_site' => null]);

    expect(status('production.debug', true))->toBe('failure')
        ->and(status('production.https', true))->toBe('warning')
        ->and(status('production.session', true))->toBe('warning')
        ->and(status('production.config-cache', true))->toBe('warning')
        ->and(status('production.route-cache', true))->toBe('warning')
        ->and(status('production.debug'))->toBe('missing');

    $this->reboot(['app.debug' => false, 'app.url' => 'https://gcego.test', 'session.driver' => 'database', 'session.same_site' => 'lax', 'session.http_only' => true, 'session.secure' => true]);

    expect(status('production.debug', true))->toBe('pass')->and(status('production.https', true))->toBe('pass')->and(status('production.session', true))->toBe('pass');
});

it('outputs JSON with a stable shape and a failing exit code when unhealthy', function (): void {
    $this->reboot(['app.debug' => true]);

    $this->artisan('gcego:studio:health', ['--json' => true])->expectsOutputToContain('"healthy"')->assertSuccessful();
    $this->artisan('gcego:studio:health', ['--production' => true])->expectsOutputToContain('UNHEALTHY')->assertFailed();

    $report = studio()->health(true)->toArray();
    expect(array_keys($report))->toBe(['healthy', 'summary', 'checks'])
        ->and(array_keys($report['checks'][0]))->toBe(['id', 'label', 'status', 'message', 'remediation', 'package', 'details']);
});
