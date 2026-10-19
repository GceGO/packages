<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationManager;
use Gcego\Pages\Navigation\PageNavigationContributor;
use Gcego\Pages\PagesConfig;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Authenticated;

beforeEach(function (): void {
    $this->world = world();
    app(Registry::class)->register(new Navigation('admin', new Group('Academic', new Item(new Property(Item::Name, 'students'), new Property(Item::Label, 'Students'), new Property(Item::Url, '/admin/students'), new Property(Item::Order, 50), new Authenticated))));
    app(NavigationManager::class)->contribute(PageNavigationContributor::class);
    app()->instance(PagesConfig::class, PagesConfig::fromArray(['routing' => ['middleware' => ['web', 'auth', 'gcego.tenant']], 'rendering' => ['navigation' => 'admin']]));
});

it('adds only the pages that opt in, merged into the navigation\'s groups', function (): void {
    $nodes = iterator_to_array(app(PageNavigationContributor::class)->nodes(), false);

    expect(array_map(static fn ($node): string => $node->nodeName(), $nodes))->toBe(['page-dashboard', 'academic'])
        ->and(array_map(static fn ($node): string => $node->nodeName(), $nodes[1]->children()->all()))->toBe(['page-institutions-members', 'page-subjects'])
        ->and($nodes[0]->get(Item::Route))->toBe('gcego.dashboard')
        ->and($nodes[1]->children()->all()[0]->get(Item::Parameters))->toBe(['institution' => ':tenant'])
        ->and($nodes[1]->children()->all()[1]->get(Item::Active))->toBe(['gcego.subjects.*']);
});

it('renders the pages in the layout sidebar, visible exactly when they may be opened, with the active page', function (): void {
    $admin = squish((string) asMember($this->world['admin'])->get('/admin/subjects/1/edit')->assertOk()->getContent());
    $reader = squish((string) asMember($this->world['reader'])->get('/admin/about')->assertOk()->getContent());

    expect($admin)->toMatch('/<a href="\/admin\/subjects"[^>]*aria-current="page"/')
        ->and(substr_count($admin, 'aria-current="page"'))->toBe(2)
        ->and($admin)->toContain('href="/admin/institutions/1/members"')
        ->and($admin)->toContain('href="/admin/students"')
        ->and($admin)->not->toContain('href="/admin/subjects/create"')
        ->and($reader)->toContain('href="/admin/subjects"')
        ->and($reader)->toContain('href="/admin"');

    $visitor = squish((string) asMember($this->world['visitor'])->get('/admin/about')->assertOk()->getContent());

    expect($visitor)->not->toContain('href="/admin/subjects"')
        ->and($visitor)->toContain('href="/admin"');
});

it('starts breadcrumbs from the navigation trail when configured', function (): void {
    app()->instance(PagesConfig::class, PagesConfig::fromArray(['routing' => ['middleware' => ['web', 'auth', 'gcego.tenant']], 'breadcrumbs' => ['navigation' => 'admin']]));
    $html = squish((string) asMember($this->world['admin'])->get('/admin/subjects/1/edit')->assertOk()->getContent());

    preg_match('/<nav aria-label="Breadcrumb".*?<\/nav>/', $html, $trail);

    expect(squish(strip_tags($trail[0] ?? '')))->toBe('Academic Subjects Chemistry Edit Chemistry')
        ->and($trail[0] ?? '')->toContain('<a href="/admin/subjects/1"');
});
