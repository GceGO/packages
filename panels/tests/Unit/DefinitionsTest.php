<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Navigation\Navigation;
use Gcego\Panels\Branding\Branding;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Exceptions\InvalidPanelException;
use Gcego\Panels\Menu\UserMenuItem;
use Gcego\Panels\Panel;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Support\SafeUrl;
use Gcego\Panels\Tests\Fixtures\Access\StaffAccess;
use Gcego\Security\Requirements\Authenticated;

/**
 * @return list<string>
 */
function panelErrors(Panel $panel): array
{
    return array_map(static fn ($error): string => $error->format(), $panel->validate()->errors());
}

it('derives sensible defaults from the name', function (): void {
    $panel = new Panel('school-admin', new Authenticated);

    expect($panel->panelName())->toBe('school-admin')
        ->and($panel->title())->toBe('School Admin')
        ->and($panel->path())->toBe('school-admin')
        ->and($panel->routeName())->toBe('school-admin')
        ->and($panel->navigationName())->toBe('panel-school-admin')
        ->and($panel->guard())->toBeNull()
        ->and($panel->tenant())->toBeNull()
        ->and($panel->isHidden())->toBeFalse()
        ->and($panel->declaresAccess())->toBeTrue()
        ->and(panelErrors($panel))->toBe([]);
});

it('reads every declared property', function (): void {
    $panel = new Panel(
        new Property(Panel::Name, 'admin'),
        new Property(Panel::Title, 'Administration'),
        new Property(Panel::Path, '/manage/admin/'),
        new Property(Panel::Domain, '{institution}.example.com'),
        new Property(Panel::RouteName, 'backoffice'),
        new Property(Panel::Guard, 'staff'),
        new Property(Panel::Middleware, ['throttle:60,1']),
        new Property(Panel::AccessPolicy, StaffAccess::class),
        new Property(Panel::Tenant, 'required'),
        new Property(Panel::Verified, true),
        new Property(Panel::Dashboard, 'overview'),
        new Property(Panel::Layout, 'topbar'),
        new Property(Panel::Theme, 'ocean'),
        new Property(Panel::Order, 5),
        new Property(Panel::Vite, ['resources/css/admin.css']),
        new ModuleReference('academics'),
        new PageReference('reports'),
        new PageReference('audit', new Property(PageReference::Navigation, false)),
        new Branding('GCEGO'),
        new UserMenuItem('Profile', 'profile'),
    );

    expect(panelErrors($panel))->toBe([])
        ->and($panel->path())->toBe('manage/admin')
        ->and($panel->domain())->toBe('{institution}.example.com')
        ->and($panel->routeName())->toBe('backoffice')
        ->and($panel->guard())->toBe('staff')
        ->and($panel->middleware())->toBe(['throttle:60,1'])
        ->and($panel->accessPolicy())->toBe(StaffAccess::class)
        ->and($panel->requiresVerifiedEmail())->toBeTrue()
        ->and($panel->modules())->toBe(['academics'])
        ->and($panel->pages())->toBe(['overview', 'reports', 'audit'])
        ->and($panel->unlistedPages())->toBe(['audit'])
        ->and($panel->branding()?->brandName())->toBe('GCEGO')
        ->and($panel->userMenu()[0]->route())->toBe('profile')
        ->and($panel->vite())->toBe(['resources/css/admin.css'])
        ->and($panel->layout())->toBe('topbar')
        ->and($panel->order())->toBe(5);
});

it('rejects invalid definitions', function (Panel $panel, string $message): void {
    expect(implode("\n", panelErrors($panel)))->toContain($message);
})->with([
    'name' => [fn () => new Panel(new Property(Panel::Name, 'Admin Panel')), 'lowercase words'],
    'path with a parameter' => [fn () => new Panel('admin', new Property(Panel::Path, 'admin/{tenant}')), 'lowercase URL segments'],
    'path traversal' => [fn () => new Panel('admin', new Property(Panel::Path, '../admin')), 'lowercase URL segments'],
    'domain' => [fn () => new Panel('admin', new Property(Panel::Domain, 'http://evil.test')), 'host name'],
    'layout' => [fn () => new Panel('admin', new Property(Panel::Layout, 'floating')), 'floating'],
    'tenant' => [fn () => new Panel('admin', new Property(Panel::Tenant, 'always')), 'always'],
    'middleware' => [fn () => new Panel('admin', new Property(Panel::Middleware, ['auth; rm -rf'])), 'middleware'],
    'vite' => [fn () => new Panel('admin', new Property(Panel::Vite, ['../secrets.css'])), 'Vite'],
    'module twice' => [fn () => new Panel('admin', new ModuleReference('academics'), new ModuleReference('academics')), 'module "academics" is referenced twice'],
    'page twice' => [fn () => new Panel('admin', new Property(Panel::Dashboard, 'overview'), new PageReference('reports'), new PageReference('reports')), 'page "reports" is referenced twice'],
    'two brandings' => [fn () => new Panel('admin', new Branding('A'), new Branding('B')), 'at most one Branding'],
    'access policy' => [fn () => new Panel('admin', new Property(Panel::AccessPolicy, stdClass::class)), 'AccessPolicy must be a class implementing'],
    'child' => [fn () => new Panel('admin', new Navigation('admin')), 'Navigation'],
]);

it('derives its navigation unless it names one', function (): void {
    $derived = [...(new Panel('admin', new Property(Panel::Title, 'Administration')))->derivedDefinitions()];

    expect($derived)->toHaveCount(1)
        ->and($derived[0])->toBeInstanceOf(Navigation::class)
        ->and($derived[0]->navigationName())->toBe('panel-admin')
        ->and([...(new Panel('admin', new Property(Panel::Navigation, 'main')))->derivedDefinitions()])->toBe([]);
});

it('accepts only root-relative or https branding URLs', function (mixed $url, bool $safe): void {
    expect(SafeUrl::asset($url))->toBe($safe);
})->with([
    ['/images/logo.svg', true],
    ['https://cdn.example.com/logo.svg', true],
    ['javascript:alert(1)', false],
    ['//evil.example.com/logo.svg', false],
    ['http://example.com/logo.svg', false],
    ['data:image/svg+xml;base64,PHN2Zz4=', false],
    ['/logo.svg" onerror="alert(1)', false],
    ['', false],
    [42, false],
]);

it('validates branding through those URL rules', function (): void {
    $branding = new Branding('GCEGO', new Property(Branding::Logo, 'javascript:alert(1)'));

    expect(implode("\n", array_map(static fn ($error): string => $error->format(), $branding->validate()->errors())))->toContain('root-relative path');
});

it('validates its configuration', function (array $config, string $message): void {
    expect(fn () => PanelsConfig::fromArray($config))->toThrow(InvalidPanelException::class, $message);
})->with([
    [['routing' => ['name_prefix' => 'Gcego']], 'name_prefix'],
    [['routing' => ['middleware' => 'web']], 'list of strings'],
    [['routing' => ['register_page_routes' => 'yes']], 'true or false'],
    [['security' => ['guard' => 'bad guard']], 'guard'],
    [['appearance' => ['default_layout' => 'floating']], 'default_layout'],
    [['user_menu' => ['Profile' => 'not a route!']], 'user_menu'],
    [['assets' => ['vite' => ['../x.css']]], 'Vite entry'],
]);

it('never queries while panels are defined', function (): void {
    $queries = queriesDuring(static fn (): Panel => new Panel('admin', new Authenticated, new ModuleReference('academics'), new Branding('GCEGO')));

    expect($queries)->toBe([]);
});
