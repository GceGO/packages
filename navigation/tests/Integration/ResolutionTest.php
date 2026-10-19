<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\NavigationManager;
use Gcego\Navigation\Resolution\ResolvedNavigation;
use Gcego\Navigation\Resolution\ResolvedNode;
use Gcego\Navigation\Tests\Fixtures\Models\AccessRequest;
use Gcego\Navigation\Tests\Fixtures\Providers\FailingBadge;
use Gcego\Navigation\Tests\Fixtures\Providers\PendingRequestsBadge;
use Gcego\Registry\Registry;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
    PendingRequestsBadge::$calls = 0;
});

/**
 * @param  list<ResolvedNode>  $nodes
 * @return list<string>
 */
function ids(array $nodes): array
{
    return array_map(static fn (ResolvedNode $node): string => $node->id, $nodes);
}

function resolvedOn(string $uri, string $navigation = 'admin'): ResolvedNavigation
{
    onPage($uri);

    return navigation()->resolved($navigation);
}

it('shows what the user may see, with URLs, on the current page', function (): void {
    $resolved = resolvedOn('/admin/subjects/1');
    $academic = $resolved->find('academic-management');
    $administration = $resolved->find('administration');

    expect(ids($resolved->nodes))->toBe(['dashboard', 'academic-management', 'administration', 'reports', $resolved->nodes[4]->id, 'documentation'])
        ->and($resolved->nodes[4]->type)->toBe('divider')
        ->and(ids($academic?->children ?? []))->toBe(['subjects', 'students', 'planning', 'topics'])
        ->and(ids($administration?->children ?? []))->toBe(['members', 'requests'])
        ->and($resolved->find('members')?->url)->toBe('/admin/institutions/1/members')
        ->and($resolved->find('dashboard')?->url)->toBe('/admin')
        ->and($resolved->find('documentation')?->url)->toBe('https://docs.example.com/gcego')
        ->and($resolved->find('vault'))->toBeNull()
        ->and($resolved->find('missing'))->toBeNull()
        ->and($resolved->find('institutions'))->toBeNull();
});

it('marks exactly one active item and expands the groups holding it', function (string $uri, ?string $active, array $trail): void {
    $resolved = resolvedOn($uri);
    $actives = [];
    $walk = static function (array $nodes) use (&$walk, &$actives): void {
        foreach ($nodes as $node) {
            if ($node->active) {
                $actives[] = $node->id;
            }

            $walk($node->children);
        }
    };
    $walk($resolved->nodes);

    expect($actives)->toBe($active === null ? [] : [$active])
        ->and(ids($resolved->trail))->toBe($trail);

    foreach ($resolved->trail as $node) {
        if ($node->isContainer()) {
            expect($node->expanded)->toBeTrue()->and($node->activeDescendant)->toBeTrue();
        }
    }
})->with([
    'exact route' => ['/admin', 'dashboard', ['dashboard']],
    'index' => ['/admin/subjects', 'subjects', ['academic-management', 'subjects']],
    'detail by pattern' => ['/admin/subjects/1', 'subjects', ['academic-management', 'subjects']],
    'edit by pattern' => ['/admin/subjects/1/edit', 'subjects', ['academic-management', 'subjects']],
    'other resource' => ['/admin/students/9', 'students', ['academic-management', 'students']],
    'path item' => ['/admin/topics', 'topics', ['academic-management', 'topics']],
    'route parameters' => ['/admin/institutions/1/members', 'members', ['administration', 'members']],
    'other tenant parameters' => ['/admin/institutions/2/members', null, []],
    'nested dropdown group' => ['/admin/reports/fees', 'fees', ['reports', 'finance', 'fees']],
    'unrelated page' => ['/admin/settings', null, []],
]);

it('keeps collapsed groups collapsed unless they hold the page', function (): void {
    expect(resolvedOn('/admin')->find('administration')?->expanded)->toBeFalse()
        ->and(resolvedOn('/admin/requests')->find('administration')?->expanded)->toBeTrue()
        ->and(resolvedOn('/admin')->find('reports')?->expanded)->toBeFalse()
        ->and(resolvedOn('/admin')->find('academic-management')?->expanded)->toBeTrue()
        ->and(resolvedOn('/admin')->find('academic-management')?->collapsible)->toBeTrue();
});

it('matches query strings only when asked', function (): void {
    app(Registry::class)->register(new Navigation('filters',
        new Item(new Property(Item::Name, 'all'), new Property(Item::Label, 'All students'), new Property(Item::Url, '/admin/students')),
        new Item(new Property(Item::Name, 'active'), new Property(Item::Label, 'Active students'), new Property(Item::Url, '/admin/students?status=active'), new Property(Item::MatchQuery, true)),
    ));

    expect(resolvedOn('/admin/students?status=active', 'filters')->active?->id)->toBe('all')
        ->and(resolvedOn('/admin/students', 'filters')->find('active')?->active)->toBeFalse();

    config()->set('gcego-navigation.active.match_query_string', true);
    app()->forgetInstance(NavigationConfig::class);

    expect(resolvedOn('/admin/students?status=active', 'filters')->active?->id)->toBe('all');
});

it('re-evaluates permissions on every request', function (): void {
    Gate::define('institution.members.manage', fn (): bool => true);
    actAs($this->world['admin']);

    expect(ids(resolvedOn('/admin')->find('administration')?->children ?? []))->toBe(['institutions', 'members', 'requests']);

    Gate::define('institution.members.manage', fn (): bool => false);
    actAs($this->world['admin']);

    expect(ids(resolvedOn('/admin')->find('administration')?->children ?? []))->toBe(['members', 'requests']);
});

it('hides tenant-only nodes outside a tenant and groups with nothing visible', function (): void {
    actAs($this->world['teacher'], null);
    $resolved = resolvedOn('/admin');

    expect($resolved->find('administration'))->toBeNull()
        ->and($resolved->find('members'))->toBeNull()
        ->and($resolved->find('vault'))->toBeNull()
        ->and($resolved->find('dashboard'))->not->toBeNull();
});

it('shows nothing to guests by default, and everything public when configured', function (): void {
    auth()->logout();
    actAs(null, null);

    expect(resolvedOn('/admin')->isEmpty())->toBeTrue();

    config()->set('gcego-navigation.visibility.default', 'public');
    app()->forgetInstance(NavigationConfig::class);

    expect(ids(resolvedOn('/admin')->nodes))->toContain('dashboard', 'academic-management', 'reports', 'documentation')
        ->and(resolvedOn('/admin')->find('administration'))->toBeNull();

    config()->set('gcego-navigation.visibility.default', 'deny');
    app()->forgetInstance(NavigationConfig::class);
    actAs($this->world['teacher']);

    expect(resolvedOn('/admin')->find('dashboard'))->toBeNull()
        ->and(ids(resolvedOn('/admin')->find('administration')?->children ?? []))->toBe(['members', 'requests']);
});

it('builds breadcrumbs from the authorized trail and trusted record labels', function (string $uri, array $crumbs): void {
    onPage($uri);

    expect(navigation()->breadcrumbs('admin')->toArray())->toBe($crumbs);
})->with([
    'record' => ['/admin/subjects/1', [
        ['label' => 'Dashboard', 'url' => '/admin', 'current' => false],
        ['label' => 'Academic Management', 'url' => null, 'current' => false],
        ['label' => 'Subjects', 'url' => '/admin/subjects', 'current' => false],
        ['label' => 'Chemistry', 'url' => '/admin/subjects/1', 'current' => true],
    ]],
    'below the record' => ['/admin/subjects/2/edit', [
        ['label' => 'Dashboard', 'url' => '/admin', 'current' => false],
        ['label' => 'Academic Management', 'url' => null, 'current' => false],
        ['label' => 'Subjects', 'url' => '/admin/subjects', 'current' => false],
        ['label' => 'Physics', 'url' => '/admin/subjects/2', 'current' => false],
        ['label' => 'Edit', 'url' => null, 'current' => true],
    ]],
    'another tenant\'s record' => ['/admin/subjects/3', [
        ['label' => 'Dashboard', 'url' => '/admin', 'current' => false],
        ['label' => 'Academic Management', 'url' => null, 'current' => false],
        ['label' => 'Subjects', 'url' => '/admin/subjects', 'current' => true],
    ]],
    'missing record' => ['/admin/subjects/99', [
        ['label' => 'Dashboard', 'url' => '/admin', 'current' => false],
        ['label' => 'Academic Management', 'url' => null, 'current' => false],
        ['label' => 'Subjects', 'url' => '/admin/subjects', 'current' => true],
    ]],
    'home' => ['/admin', [['label' => 'Dashboard', 'url' => '/admin', 'current' => true]]],
    'no active item' => ['/admin/settings', []],
]);

it('leaves out unauthorized ancestors from breadcrumbs', function (): void {
    app(Registry::class)->register(new Navigation('staff', new Property(Navigation::Home, 'home'),
        new Item(new Property(Item::Name, 'home'), new Property(Item::Label, 'Home'), new Property(Item::Url, '/admin'), new Property(Item::Permission, 'staff.home')),
        new Group('Records', new Item(new Property(Item::Name, 'topics'), new Property(Item::Label, 'Topics'), new Property(Item::Url, '/admin/topics'))),
    ));
    onPage('/admin/topics');

    expect(navigation()->breadcrumbs('staff')->toArray())->toBe([
        ['label' => 'Records', 'url' => null, 'current' => false],
        ['label' => 'Topics', 'url' => '/admin/topics', 'current' => true],
    ]);

    config()->set('gcego-navigation.breadcrumbs.enabled', false);
    app()->forgetInstance(NavigationConfig::class);
    app()->forgetInstance(NavigationManager::class);

    expect(navigation()->breadcrumbs('staff')->isEmpty())->toBeTrue();
});

it('resolves badges lazily, as the user, in the current tenant', function (): void {
    expect(PendingRequestsBadge::$calls)->toBe(0);

    $badge = resolvedOn('/admin')->find('requests')?->badge;

    expect(PendingRequestsBadge::$calls)->toBe(1)
        ->and($badge?->text)->toBe('3')
        ->and($badge?->label)->toBe('3 pending requests')
        ->and(resolvedOn('/admin')->find('fees')?->badge?->text)->toBe('New');

    actAs($this->world['south'], 2);

    expect(resolvedOn('/admin')->find('requests')?->badge?->text)->toBe('1');

    actAs($this->world['teacher'], null);
    PendingRequestsBadge::$calls = 0;
    resolvedOn('/admin');

    expect(PendingRequestsBadge::$calls)->toBe(0);
});

it('counts the records the user may see as a badge, in the current tenant, hiding zero', function (): void {
    app(Registry::class)->register(new Navigation('counted', new Item(new Property(Item::Name, 'requests'), new Property(Item::Label, 'Requests'), new Property(Item::Url, '/admin/requests'), new Property(Item::BadgeCount, AccessRequest::class), new Property(Item::BadgeLabel, 'requests'))));

    $badge = resolvedOn('/admin', 'counted')->find('requests')?->badge;

    expect($badge?->text)->toBe('3')
        ->and($badge?->label)->toBe('3 requests')
        ->and($badge?->variant)->toBe('default');

    actAs($this->world['south'], 2);
    expect(resolvedOn('/admin', 'counted')->find('requests')?->badge?->text)->toBe('1');

    AccessRequest::query()->delete();
    expect(resolvedOn('/admin', 'counted')->find('requests')?->badge)->toBeNull();
});

it('reports failing badge providers and renders without their badge', function (): void {
    Exceptions::fake();
    app(Registry::class)->register(new Navigation('broken', new Item(new Property(Item::Name, 'requests'), new Property(Item::Label, 'Requests'), new Property(Item::Url, '/admin/requests'), new Property(Item::Badge, FailingBadge::class))));

    expect(resolvedOn('/admin', 'broken')->find('requests')?->badge)->toBeNull()
        ->and(navigation()->render('broken')->toHtml())->not->toContain('SQLSTATE');

    Exceptions::assertReported(RuntimeException::class);
});

it('caches badges per user and tenant only when asked, and forgets them', function (): void {
    app(Registry::class)->register(new Navigation('cached', new Item(new Property(Item::Name, 'requests'), new Property(Item::Label, 'Requests'), new Property(Item::Url, '/admin/requests'), new Property(Item::Badge, PendingRequestsBadge::class), new Property(Item::BadgeTtl, 60))));

    expect(resolvedOn('/admin', 'cached')->find('requests')?->badge?->text)->toBe('3');
    actAs($this->world['teacher']);
    expect(resolvedOn('/admin', 'cached')->find('requests')?->badge?->text)->toBe('3')
        ->and(PendingRequestsBadge::$calls)->toBe(1);

    actAs($this->world['south'], 2);
    expect(resolvedOn('/admin', 'cached')->find('requests')?->badge?->text)->toBe('1')
        ->and(PendingRequestsBadge::$calls)->toBe(2);

    AccessRequest::query()->create(['institution_id' => 1]);
    actAs($this->world['teacher']);
    expect(resolvedOn('/admin', 'cached')->find('requests')?->badge?->text)->toBe('3');

    navigation()->forgetBadges('cached');
    actAs($this->world['teacher']);
    expect(resolvedOn('/admin', 'cached')->find('requests')?->badge?->text)->toBe('4');
});
