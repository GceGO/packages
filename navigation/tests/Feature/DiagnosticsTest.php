<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Diagnostics\NavigationDiagnostic;
use Gcego\Navigation\Diagnostics\NavigationInspector;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\NavigationManager;
use Gcego\Navigation\Tests\Fixtures\Providers\PendingRequestsBadge;
use Gcego\Security\Permissions\Permission;

it('inspects a registered navigation without authorizing, routing badges or querying', function (): void {
    PendingRequestsBadge::$calls = 0;
    $queries = queriesDuring(fn () => $this->report = navigation()->inspect('admin'));
    $routes = collect($this->report->diagnostics)->where('check', 'routes');

    expect($queries)->toBe([])
        ->and(PendingRequestsBadge::$calls)->toBe(0)
        ->and($this->report->identity)->toBe('navigation:admin')
        ->and($this->report->isHealthy())->toBeFalse()
        ->and($routes->pluck('message')->all())->toBe(['Route "admin.missing" does not exist; the item is never shown.'])
        ->and($routes->first()?->path)->toBe('missing')
        ->and(collect($this->report->nodes)->firstWhere('id', 'institutions')['requirements'])->toBe([Permission::class])
        ->and(collect($this->report->diagnostics)->firstWhere('check', 'authorization')?->message)->toContain('dashboard, subjects, students, topics')
        ->and(json_encode($this->report->toArray()))->not->toContain('pending requests');
});

it('reports problems by check', function (): void {
    config()->set('gcego-navigation.diagnostics', ['max_nodes' => 3, 'max_badges' => 1]);
    app()->forgetInstance(NavigationConfig::class);
    app()->forgetInstance(NavigationInspector::class);
    app()->forgetInstance(NavigationManager::class);

    $report = navigation()->inspect(new Navigation('broken', new Permission('staff'),
        new Item(new Property(Item::Label, 'Students'), new Property(Item::Url, '/a'), new Property(Item::Icon, 'unicorn'), new Property(Item::Active, 'nothing.matches.*'), new Property(Item::Badge, PendingRequestsBadge::class)),
        new Item(new Property(Item::Name, 'again'), new Property(Item::Label, 'Students'), new Property(Item::Url, '/b'), new Property(Item::Badge, PendingRequestsBadge::class)),
        new Group('Styled', new Item('C', '/c'), new Tailwind(new Slot('tablist', ['p-4']))),
    ));

    expect($report->checks())->toContain('icons', 'routes', 'styling', 'accessibility', 'performance')
        ->and($report->checks())->not->toContain('authorization')
        ->and(collect($report->errors())->pluck('check')->all())->toBe(['styling'])
        ->and(collect($report->warnings())->pluck('message')->implode(' '))->toContain('Icon "unicorn"', 'matches no route', 'labelled "Students"', '4 nodes', '2 badge providers');
});

it('reports structural problems and unknown identities', function (): void {
    expect(navigation()->inspect(new Navigation('dup', new Item('A', '/a'), new Group('G', new Item('A', '/b'))))->checks())->toContain('schema', 'structure')
        ->and(navigation()->inspect('nope')->checks())->toBe(['registry'])
        ->and(navigation()->inspect(new Navigation('ok', new Permission('x'), new Item('A', '/a')))->isHealthy())->toBeTrue()
        ->and(navigation()->inspect(new Navigation('ok2', new Item('A', '/a')))->diagnostics[0]->level)->toBe(NavigationDiagnostic::Warning);
});
