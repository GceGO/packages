<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Contracts\PageComponent;
use Gcego\Pages\Diagnostics\PageInspector;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Tests\Fixtures\Models\User;
use Gcego\Pages\Types\ViewPage;
use Gcego\Registry\Registry;
use Gcego\Security\Requirements\Authenticated;

it('reports a healthy page: identity, type, route and components, without opening it', function (): void {
    world();
    $report = null;
    $queries = queriesDuring(function () use (&$report): void {
        $report = pages()->inspect('subjects.view');
    });

    expect($queries)->toBe([])
        ->and($report?->isHealthy())->toBeTrue()
        ->and($report?->warnings())->toBe([])
        ->and($report?->identity)->toBe('page:subjects-view')
        ->and($report?->type)->toBe('view')
        ->and($report?->route)->toBe(['path' => 'subjects/{record}', 'name' => 'gcego.subjects.view', 'registered' => ['gcego.subjects.view'], 'record' => true, 'record_key' => 'key', 'middleware' => ['web', 'auth', 'gcego.tenant']])
        ->and($report?->components[0])->toBe(['path' => '#0', 'type' => 'pages.view', 'depth' => 0, 'requirements' => ['permission subjects.view']])
        ->and(json_encode($report?->toArray()))->not->toContain('Chemistry');
});

it('finds problems in page definitions', function (Page $page, string $check, string $level): void {
    $report = pages()->inspect($page);

    expect($report->checks())->toContain($check)
        ->and(array_values(array_filter($report->diagnostics, static fn ($diagnostic): bool => $diagnostic->check === $check))[0]->level)->toBe($level);
})->with([
    'no requirement under "deny"' => [fn (): Page => new Page('open', 'Open', new Content('x')), 'authorization.default', 'error'],
    'link to a missing page' => [fn (): Page => new Page('linking', 'Linking', new Authenticated, new PageLink('Go', 'nowhere')), 'link.target', 'error'],
    'link to a record page without a record' => [fn (): Page => new Page('linking', 'Linking', new Authenticated, new PageLink('Edit', 'subjects.edit')), 'link.record', 'warning'],
    'record without policy' => [fn (): Page => new ViewPage('people.view', 'Person', new Authenticated, new Source(User::class), new Infolist(new TextEntry('name'))), 'record.policy', 'error'],
    'route conflict with another page' => [fn (): Page => new Page(new Property(Page::Name, 'twin'), new Property(Page::Path, 'subjects/create'), new Authenticated), 'route.conflict', 'error'],
    'unknown styling slot' => [fn (): Page => new Page('styled', 'Styled', new Authenticated, new Tailwind(new Slot('ribbon', ['p-4']))), 'styling.slot', 'error'],
    'missing renderer' => [fn (): Page => new Page('custom', 'Custom', new Authenticated, new class extends Component implements PageComponent {}), 'renderer.missing', 'error'],
    'invalid definition' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Path, 'x/{record}')), 'definition', 'error'],
    'unknown parent' => [fn (): Page => new Page(new Property(Page::Name, 'child'), new Property(Page::Parent, 'ghost'), new Authenticated), 'breadcrumbs.parent', 'warning'],
    'registered after routes were registered' => [function (): Page {
        $page = new Page('late', 'Late', new Authenticated);
        app(Registry::class)->register($page);

        return $page;
    }, 'route.missing', 'warning'],
]);

it('reports routes taken by application routes', function (): void {
    app('router')->get('/admin/legacy', static fn (): string => '')->name('gcego.legacy');
    $report = pages()->inspect(new Page(new Property(Page::Name, 'legacy'), new Property(Page::Path, 'legacy'), new Authenticated));

    expect(array_map(static fn ($diagnostic): string => $diagnostic->message, array_values(array_filter($report->diagnostics, static fn ($diagnostic): bool => $diagnostic->check === 'route.conflict'))))->toBe([
        'route name "gcego.legacy" is already used by route "admin/legacy".',
        'GET /admin/legacy is already answered by route "gcego.legacy".',
    ]);
});

it('warns about large pages and many Livewire hosts', function (): void {
    app()->instance(PagesConfig::class, PagesConfig::fromArray(['diagnostics' => ['max_components' => 3, 'max_livewire_hosts' => 2]]));
    app()->forgetInstance(PageInspector::class);

    expect(pages()->inspect('subjects.view')->checks())->toContain('performance.components', 'performance.livewire');
});

it('inspects every registered page', function (): void {
    app(Registry::class)->register(new Page('broken', 'Broken', new Content('No requirement.')));
    $reports = pages()->inspectAll();
    $unhealthy = array_values(array_map(static fn ($report): ?string => $report->identity, array_filter($reports, static fn ($report): bool => ! $report->isHealthy())));

    expect($reports)->toHaveCount(12)
        ->and($unhealthy)->toBe(['page:broken']);
});
