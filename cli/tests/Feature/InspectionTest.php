<?php

declare(strict_types=1);

use Gcego\Cli\CliManager;
use Gcego\Cli\Contracts\DefinitionInspector;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Security\Requirements\Ability;

beforeEach(function (): void {
    gcego('gcego:make-module', ['name' => 'Academics']);
    gcego('gcego:make-resource', ['name' => 'Subject', '--module' => 'academics', '--model' => Subject::class]);
    gcego('gcego:make-panel', ['name' => 'Admin', '--module' => ['academics']]);
    gcego('gcego:make-blueprint', ['name' => 'Subjects', '--model' => Subject::class]);
    $this->reboot();
});

it('lists definitions by type, with resources per module', function (): void {
    $lists = gcegoJson('gcego:list')['lists'];

    expect($lists['panels']['items'])->toBe(['panel:admin'])->and($lists['modules']['items'])->toBe(['module:academics'])
        ->and($lists['resources']['items'])->toBe(['academics.subjects'])->and($lists['blueprints']['items'])->toBe(['blueprint:subjects'])
        ->and($lists['pages']['items'])->toContain('page:academics-subjects', 'page:academics-overview')
        ->and(gcegoJson('gcego:list', ['--type' => 'modules'])['lists'])->toHaveKeys(['modules'])->toHaveCount(1);
});

it('shows the registry with sources, roots, cache status, paging and failures', function (): void {
    $registry = gcegoJson('gcego:registry', ['--kind' => 'page', '--limit' => 2]);

    expect($registry['total'])->toBe(5)->and($registry['definitions'])->toHaveCount(2)
        ->and($registry['definitions'][0]['source'])->toBe('app/GCEGo/Modules/Academics/Module.php')
        ->and($registry['cache']['enabled'])->toBeFalse()->and($registry['failures'])->toBe([])
        ->and(collect($registry['roots'])->pluck('path'))->toContain('app/GCEGo/Panels');

    @mkdir(ws('app/GCEGo/Pages'));
    file_put_contents(ws('app/GCEGo/Pages/broken.php'), "<?php\nreturn new Gcego\\Pages\\Page(new Gcego\\Components\\Properties\\Property(Gcego\\Pages\\Page::Name, 'Bad Name'));\n");
    [$code, $output] = gcego('gcego:registry');

    expect($code)->toBe(ExitCode::ValidationFailed)->and($output)->toContain('app/GCEGo/Pages/broken.php')->toContain('A page needs a Name');
});

it('inspects a module, a panel and a blueprint through their packages\' inspectors', function (): void {
    $module = gcegoJson('gcego:inspect', ['identity' => 'module:academics']);
    $panel = gcegoJson('gcego:inspect', ['identity' => 'panel:admin']);
    $blueprint = gcegoJson('gcego:inspect', ['identity' => 'blueprint:subjects']);

    expect($module['valid'])->toBeTrue()->and($module['source'])->toBe('app/GCEGo/Modules/Academics/Module.php')
        ->and($module['integration']['resources']['subjects']['operations'])->toBe(['index', 'create', 'view', 'edit'])
        ->and(collect($module['children'])->pluck('class'))->toContain(Gcego\Modules\Resource::class)
        ->and($panel['integration']['path'])->toBe('/admin')->and($panel['integration']['modules'])->toContain('academics')
        ->and($panel['requirements'])->toBe([Ability::class])
        ->and($blueprint['properties']['name'])->toBe('subjects')->and($blueprint['valid'])->toBeTrue();

    [$code, $output] = gcego('gcego:inspect', ['identity' => 'module:academic']);
    expect($code)->toBe(ExitCode::MissingDependency)->and($output)->toContain('Did you mean module:academics');
});

it('adds sections of custom inspectors', function (): void {
    app(CliManager::class)->inspector(new class implements DefinitionInspector
    {
        public function id(): string
        {
            return 'acme.owners';
        }

        public function kinds(): array
        {
            return ['module'];
        }

        public function inspect(object $definition, DefinitionDescriptor $descriptor): array
        {
            return ['owner' => 'Registrar', 'api_token' => 'secret-value'];
        }
    });

    $report = gcegoJson('gcego:inspect', ['identity' => 'module:academics']);
    expect($report['sections']['acme.owners'])->toBe(['owner' => 'Registrar', 'api_token' => '[redacted]']);
});

it('lists GCEGO routes with panel and module filters, without registering any', function (): void {
    $before = count(app('router')->getRoutes()->getRoutes());
    $routes = gcegoJson('gcego:routes', ['--panel' => 'admin', '--module' => 'academics']);

    expect(collect($routes['routes'])->pluck('uri')->all())->toBe(['/admin/academics/overview', '/admin/subjects', '/admin/subjects/create', '/admin/subjects/{record}', '/admin/subjects/{record}/edit'])
        ->and($routes['routes'][0]['middleware'])->toContain('auth', 'gcego.panel:admin')
        ->and(count(app('router')->getRoutes()->getRoutes()))->toBe($before)
        ->and(gcegoJson('gcego:routes', ['--panel' => 'other'])['routes'])->toBe([]);
});

it('reports packages, assets and caches', function (): void {
    $this->reboot(['gcego-studio.assets.vite' => ['resources/css/app.css']]);
    $packages = gcegoJson('gcego:packages');
    $assets = gcego('gcego:assets');
    $caches = gcegoJson('gcego:cache-status')['caches'];

    expect(collect($packages['packages'])->where('required', true))->toHaveCount(18)->and($packages['compatible'])->toBeTrue()
        ->and(collect($packages['packages'])->firstWhere('id', 'gcego/settings')['installed'])->toBeTrue()
        ->and($assets[0])->toBe(ExitCode::DiagnosticFailure)->and($assets[1])->toContain('No Vite manifest was found')
        ->and(collect($caches)->pluck('cache'))->toContain('gcego/registry definitions', 'gcego/settings settings values')
        ->and(collect($caches)->firstWhere('cache', 'gcego/registry definitions')['status'])->toBe('disabled');

    @mkdir(ws('resources/css'), 0777, true);
    @mkdir(ws('public/build/assets'), 0777, true);
    file_put_contents(ws('resources/css/app.css'), "@import \"tailwindcss\";\n@import \"../../vendor/gcego/chroma/resources/css/gcego.css\";\n");
    file_put_contents(ws('public/build/assets/app.css'), '');
    file_put_contents(ws('public/build/manifest.json'), (string) json_encode(['resources/css/app.css' => ['file' => 'assets/app.css']]));

    expect(gcego('gcego:assets')[0])->toBe(ExitCode::Success);
});
