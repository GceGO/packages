<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Sidebar;
use Gcego\Luminaries\Facades\Luminaries;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Exceptions\InvalidNavigationException;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\NavigationManager;
use Gcego\Navigation\NavigationServiceProvider;
use Gcego\Navigation\Tests\Fixtures\Models\Student;
use Gcego\Navigation\Tests\Fixtures\Providers\EveryNavigationContributor;
use Gcego\Navigation\Tests\Fixtures\Providers\ReportsContributor;
use Gcego\Registry\Registry;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Runtime;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\Table;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('renders inside layouts and blueprints through the runtime', function (): void {
    app(Registry::class)->register(new Blueprint('students', new Ability('viewAny', Student::class), new Source(Student::class),
        new Sidebar(
            new Aside('Menu', new Navigation('side', new Property(Navigation::Label, 'Students menu'),
                new Item(new Property(Item::Name, 'list'), new Property(Item::Label, 'All students'), new Property(Item::Route, 'admin.students.index')),
                new Item('Subjects', 'admin.subjects.index'),
            )),
            new Main(new Table(new Text('name', 'Name'))),
        ),
        new Navigation('crumbs', new Property(Navigation::Display, 'breadcrumbs'), new Item(new Property(Item::Name, 'list'), new Property(Item::Label, 'All students'), new Property(Item::Route, 'admin.students.index'))),
    ));
    onPage('/admin/students');
    $html = squish(app(Runtime::class)->renderAuthorized('blueprint:students')->toHtml());

    expect($html)->toMatch('/<aside id="[^"]+" aria-label="Menu"[^>]*>\s*<nav id="[^"]+" aria-label="Students menu"/')
        ->and($html)->toMatch('/<a href="\/admin\/students"[^>]*aria-current="page"/')
        ->and($html)->toContain('<nav aria-label="Breadcrumb"')
        ->and(substr_count($html, 'wire:name="gcego-table"'))->toBe(1)
        ->and(app(Registry::class)->get('blueprint:students')->parts('navigation'))->toHaveCount(2);
});

it('merges contributions into existing groups and the root, in order', function (): void {
    navigation()->contribute(ReportsContributor::class);
    onPage('/admin');
    $resolved = navigation()->resolved('admin');

    expect(array_map(static fn ($node): string => $node->id, $resolved->find('administration')?->children ?? []))->toBe(['audit', 'members', 'requests'])
        ->and($resolved->nodes[array_key_last($resolved->nodes)]->id)->toBe('help')
        ->and(fn () => navigation()->contribute(Item::class))->toThrow(InvalidNavigationException::class, 'must implement');
});

it('lets one contributor add to several navigations', function (): void {
    navigation()->contribute(EveryNavigationContributor::class);
    app(Registry::class)->register(new Navigation('school', new Item(new Property(Item::Name, 'home'), new Property(Item::Label, 'Home'), new Property(Item::Url, '/school'))));

    expect(navigation()->tree('school')->find('status')?->component->get('label'))->toBe('Status of school')
        ->and(navigation()->tree('admin')->find('status'))->toBeNull();
});

it('reads contributors from configuration, lazily', function (): void {
    config()->set('gcego-navigation.contributors', [ReportsContributor::class]);
    app()->forgetInstance(NavigationConfig::class);
    app()->forgetInstance(NavigationManager::class);
    onPage('/admin/help');

    expect(navigation()->resolved('admin')->active?->id)->toBe('help');
});

it('caches the structure per definition and resolves per request', function (): void {
    $first = navigation()->tree('admin');

    onPage('/admin');
    $dashboard = navigation()->resolved('admin');
    onPage('/admin/topics');
    $topics = navigation()->resolved('admin');

    expect(navigation()->tree('admin'))->toBe($first)
        ->and($dashboard->active?->id)->toBe('dashboard')
        ->and($topics->active?->id)->toBe('topics')
        ->and($dashboard)->not->toBe($topics);
});

it('registers its package, kind, renderer, slots and icons without queries', function (): void {
    $queries = queriesDuring(function (): void {
        (new NavigationServiceProvider(app()))->register();
    });

    expect($queries)->toBe([])
        ->and(app(Gcego\Core\Gcego::class)->hasPackage('gcego/navigation'))->toBeTrue()
        ->and(app(RendererRegistry::class)->has(Navigation::class))->toBeTrue()
        ->and(array_keys(app(SlotRegistry::class)->for(Navigation::class) ?? []))->toBe(array_keys(Navigation::styleSlots()))
        ->and(array_keys(app(SlotRegistry::class)->for(Item::class) ?? []))->toBe(['item', 'icon', 'label', 'badge'])
        ->and(app(Icons::class)->has('bars-3'))->toBeTrue()
        ->and(app(Icons::class)->has('arrow-top-right-on-square'))->toBeTrue()
        ->and(app(Registry::class)->get('navigation:admin'))->toBeInstanceOf(Navigation::class)
        ->and(config('gcego-navigation.groups.max_depth'))->toBe(3);
});

it('fails fast on invalid configuration', function (): void {
    config()->set('gcego-navigation.visibility.default', 'everyone');
    app()->forgetInstance(NavigationConfig::class);

    expect(fn () => app(NavigationConfig::class))->toThrow(InvalidNavigationException::class, 'visibility.default');
});

it('renders semantic icon aliases through gcego/luminaries and follows global overrides', function (): void {
    app(Registry::class)->register(new Navigation('icons', new Item(new Property(Item::Name, 'dashboard'), new Property(Item::Label, 'Dashboard'), new Property(Item::Url, '/dashboard'), new Property(Item::Icon, 'navigation.dashboard'))));
    $default = navigation()->render('icons')->toHtml();
    Luminaries::alias('navigation.dashboard', 'lucide:layout-dashboard', override: true);

    expect($default)->toContain('data-gcego-icon="heroicon:squares-2x2"')
        ->and(navigation()->render('icons')->toHtml())->toContain('data-gcego-icon="lucide:layout-dashboard"');
});
