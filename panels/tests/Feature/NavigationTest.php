<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationManager;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Definitions\PageReference;
use Gcego\Panels\Panel;
use Gcego\Panels\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\Requirements\Authenticated;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * @return list<string> "label -> href" of every link the user sees.
 */
function navigationLinks(string $navigation): array
{
    $links = [];
    $walk = static function (array $nodes) use (&$walk, &$links): void {
        foreach ($nodes as $node) {
            if ($node->isLink()) {
                $links[] = $node->label.' -> '.$node->url;
            }

            $walk($node->children);
        }
    };
    $walk(app(NavigationManager::class)->resolved($navigation)->nodes);

    return $links;
}

it('builds each panel\'s navigation from its dashboard, pages and modules', function (): void {
    $world = world();
    asMember($world['admin'])->get('/admin/overview');
    onPage('/admin/overview');
    actAs($world['admin']);

    expect(navigationLinks('panel-admin'))->toBe([
        'Overview -> /admin/overview',
        'Reports -> /admin/reports',
        'Subjects -> /admin/subjects',
        'Topics -> /admin/topics',
    ]);
});

it('links tenant panels to the current tenant', function (): void {
    $world = world();
    onPage('/school/1/subjects');
    actAs($world['reader']);
    app(UrlGenerator::class)->defaults(['institution' => '1']);

    expect(navigationLinks('panel-school'))->toBe([
        'Subjects -> /school/1/subjects',
        'Topics -> /school/1/topics',
    ]);
});

it('shows each user only the pages they may open', function (): void {
    $world = world();
    onPage('/staff');
    actAs($world['editor']);

    expect(navigationLinks('panel-staff'))->toBe([]);

    actAs($world['admin']);

    expect(navigationLinks('panel-staff'))->toBe(['Reports -> /staff/reports']);
});

it('groups pages by their navigation group and modules by module', function (): void {
    $tree = app(NavigationManager::class)->tree('panel-admin');

    expect($tree->find('group-insights')?->component->get('label'))->toBe('Insights')
        ->and($tree->find('module-academics')?->component->get('label'))->toBe('Academic Management')
        ->and($tree->find('dashboard'))->not->toBeNull();
});

it('counts a resource\'s records as its badge, as the user, when it opts in', function (): void {
    $world = world();
    onPage('/admin/overview');
    actAs($world['admin']);
    $resolved = app(NavigationManager::class)->resolved('panel-admin');

    // Only the subjects the admin may see: one of the four belongs to another institution.
    expect(Subject::query()->count())->toBe(4)
        ->and($resolved->find('resource-subjects')?->badge?->text)->toBe('3')
        ->and($resolved->find('resource-subjects')?->badge?->variant)->toBe('success')
        ->and($resolved->find('resource-subjects')?->badge?->label)->toBe('3 subjects')
        ->and($resolved->find('resource-topics')?->badge)->toBeNull();
});

it('marks the current page and its resource pages active', function (): void {
    $world = world();
    $html = html(asMember($world['admin'])->get('/admin/subjects/create'));

    expect($html)->toMatch('/<a href="\/admin\/subjects"[^>]*aria-current="page"/');
});

it('leaves out pages a panel opts out of, and pages that need a record', function (): void {
    $world = world();
    routePanel(new Panel(
        new Property(Panel::Name, 'quiet'),
        new Authenticated,
        new PageReference('help', new Property(PageReference::Navigation, false)),
        new PageReference('reports'),
    ));
    onPage('/quiet/reports');
    actAs($world['admin']);

    expect(navigationLinks('panel-quiet'))->toBe(['Reports -> /quiet/reports']);
});

it('uses a navigation the panel names, with its contributions', function (): void {
    $world = world();
    app(RegistryInterface::class)->register(new Navigation('main', new Item(new Property(Item::Name, 'site'), new Property(Item::Label, 'Website'), new Property(Item::Url, '/'))));
    routePanel(new Panel(new Property(Panel::Name, 'named'), new Property(Panel::Navigation, 'main'), new Authenticated, new ModuleReference('academics')));
    onPage('/named/subjects');
    actAs($world['admin']);

    expect(navigationLinks('main'))->toBe(['Website -> /', 'Subjects -> /named/subjects', 'Topics -> /named/topics']);
});
