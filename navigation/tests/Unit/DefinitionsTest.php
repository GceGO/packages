<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Collections\ComponentCollection;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Navigation\Components\Divider;
use Gcego\Navigation\Components\Dropdown;
use Gcego\Navigation\Components\ExternalLink;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Heading;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Exceptions\DuplicateNavigationItemException;
use Gcego\Navigation\Exceptions\InvalidNavigationException;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\Registry\NavigationKind;
use Gcego\Navigation\Tests\Fixtures\Definitions;
use Gcego\Navigation\Tests\Fixtures\Models\Student;
use Gcego\Navigation\Tests\Fixtures\Providers\PendingRequestsBadge;
use Gcego\Navigation\Tree\NavigationTree;
use Gcego\Navigation\Tree\NavigationTreeBuilder;
use Gcego\Navigation\Tree\Node;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;

function errorsOf(Component $component): string
{
    return implode(' ', array_map(strval(...), $component->validate()->errors()));
}

function build(Navigation $navigation, int $maxDepth = 3): NavigationTree
{
    return (new NavigationTreeBuilder)->build($navigation, 'navigation:test', $maxDepth);
}

it('defines navigations at every level of the documented API without queries', function (): void {
    $queries = queriesDuring(function (): void {
        $this->levels = [
            new Navigation(new Item('Dashboard', '/admin'), new Item('Students', '/admin/students'), new Item('Subjects', '/admin/subjects'), new Item('Institutions', '/admin/institutions')),
            new Navigation(
                new Group('Academic Management', new Item('Students', '/admin/students'), new Item('Subjects', '/admin/subjects'), new Item('Topics', '/admin/topics')),
                new Group('Administration', new Item('Institutions', '/admin/institutions'), new Item('Users', '/admin/users')),
            ),
            new Navigation(new Item(new Property(Item::Name, 'students'), new Property(Item::Label, 'Students'), new Property(Item::Route, 'admin.students.index'), new Property(Item::Icon, 'users'), new Property(Item::Order, 20))),
            new Navigation(new Item(new Property(Item::Name, 'institutions'), new Property(Item::Label, 'Institutions'), new Property(Item::Route, 'admin.institutions.index'), new Property(Item::Permission, 'institution.members.manage'))),
            new Navigation(new Item(new Property(Item::Name, 'requests'), new Property(Item::Label, 'Requests'), new Property(Item::Route, 'admin.requests.index'), new Property(Item::Badge, PendingRequestsBadge::class))),
            new Navigation(new Item('Dashboard', '/admin'), new Item('Students', '/admin/students'), new Tailwind(
                new Slot(Tailwind::Wrapper, ['flex', 'flex-col', 'gap-1', 'p-3']),
                new Slot(Navigation::ItemSlot, ['flex', 'items-center', 'gap-3', 'rounded-xl', 'px-3', 'py-2.5', 'text-sm', 'font-medium', 'transition-colors']),
            )),
            Definitions::admin(),
        ];
    });

    expect($queries)->toBe([]);

    foreach ($this->levels as $level) {
        expect(errorsOf($level))->toBe('');
    }
});

it('reads a path or a route from the shorthand', function (): void {
    $path = new Item('Students', '/admin/students');
    $route = new Item('Students', 'admin.students.index');
    $url = new Item('Portal', 'https://portal.example.com/a?b=1');

    expect($path->url())->toBe('/admin/students')->and($path->routeName())->toBeNull()
        ->and($route->routeName())->toBe('admin.students.index')->and($route->url())->toBeNull()
        ->and($url->url())->toBe('https://portal.example.com/a?b=1')
        ->and($path->nodeName())->toBe('students')
        ->and((new Group('Academic Management'))->nodeName())->toBe('academic-management')
        ->and((new Navigation('admin'))->navigationName())->toBe('admin')
        ->and((new Navigation(new Property(Navigation::Label, 'Staff Portal')))->navigationName())->toBe('staff_portal');
});

it('rejects invalid nodes', function (Component $component, string $message): void {
    expect(errorsOf($component))->toContain($message);
})->with([
    'no link' => [fn () => new Item(new Property(Item::Label, 'Nowhere')), 'an item needs a route or a URL'],
    'javascript url' => [fn () => new Item('Evil', 'javascript:alert(1)'), 'a route name, a root-relative path or an http(s) URL'],
    'javascript url property' => [fn () => new Item(new Property(Item::Label, 'Evil'), new Property(Item::Url, 'javascript:alert(1)')), 'a root-relative path'],
    'data url' => [fn () => new ExternalLink('Evil', 'data:text/html,<script>'), 'an http(s) or mailto URL'],
    'relative external' => [fn () => new ExternalLink('Local', '/admin'), 'an http(s) or mailto URL'],
    'protocol relative' => [fn () => new Item('Evil', '//evil.example.com'), 'a route name, a root-relative path'],
    'unknown badge class' => [fn () => new Item(new Property(Item::Label, 'A'), new Property(Item::Url, '/a'), new Property(Item::Badge, 'App\\Badges\\Nope')), 'a NavigationBadgeProvider class'],
    'non-provider badge class' => [fn () => new Item(new Property(Item::Label, 'A'), new Property(Item::Url, '/a'), new Property(Item::Badge, Item::class)), 'a NavigationBadgeProvider class'],
    'active pattern' => [fn () => new Item(new Property(Item::Label, 'A'), new Property(Item::Url, '/a'), new Property(Item::Active, 'admin.<x>')), 'route-name or path patterns'],
    'permission' => [fn () => new Item(new Property(Item::Label, 'A'), new Property(Item::Url, '/a'), new Property(Item::Permission, 'not a permission')), 'a permission name'],
    'parameters' => [fn () => new Item(new Property(Item::Label, 'A'), new Property(Item::Route, 'a'), new Property(Item::Parameters, [['nested']])), 'route parameters keyed by name'],
    'name' => [fn () => new Item(new Property(Item::Name, 'Bad Name'), new Property(Item::Label, 'A'), new Property(Item::Url, '/a')), 'a lowercase identifier'],
    'nested navigation' => [fn () => new Navigation('a', new Group('G', new Navigation('b', new Item('A', '/a')))), 'a navigation cannot'],
    'non-node child' => [fn () => new Navigation('a', new Component(new Property(Component::Label, 'Loose'))), 'does not accept a child'],
    'empty group' => [fn () => new Group('Empty'), 'too few children'],
    'duplicates' => [fn () => new Navigation('a', new Item('Students', '/a'), new Group('G', new Item('Students', '/b'))), 'two nodes are named "students"'],
    'item children' => [fn () => new Item('A', '/a', new Item('B', '/b')), 'does not accept child components'],
    'display' => [fn () => new Navigation(new Property(Navigation::Display, 'floating')), 'display'],
]);

it('is immutable and repeatable', function (): void {
    $item = new Item('Students', '/admin/students');

    expect($item->normalize())->toBe($item->normalize())
        ->and(errorsOf($item))->toBe(errorsOf($item))
        ->and(fn () => (new ReflectionProperty(Node::class, 'id'))->setValue(build(new Navigation(new Item('A', '/a')))->nodes[0], 'b'))->toThrow(Error::class);
});

it('orders siblings by Order, then declaration', function (): void {
    $tree = build(new Navigation(
        new Item('Third', '/c', new Property(Item::Order, 5)),
        new Item('First', '/a'),
        new Item('Second', '/b'),
        new Item('Last', '/d', new Property(Item::Order, 9)),
        new Item('Zeroth', '/z', new Property(Item::Order, -1)),
    ));

    expect(array_map(static fn ($node): string => $node->id, $tree->nodes))->toBe(['zeroth', 'first', 'second', 'third', 'last'])
        ->and(array_map(static fn ($node): string => $node->id, build(Definitions::admin())->find('academic-management')?->children ?? []))->toBe(['subjects', 'students', 'planning', 'topics']);
});

it('nests groups up to the maximum depth', function (): void {
    $nested = new Navigation(new Group('One', new Group('Two', new Group('Three', new Item('Deep', '/deep')))));

    expect(build($nested)->find('deep')?->depth)->toBe(4)
        ->and(fn () => build($nested, 2))->toThrow(InvalidNavigationException::class, 'group "three" is nested 3 levels deep; gcego-navigation.groups.max_depth allows 2');
});

it('detects duplicates and cycles before anything renders', function (): void {
    $shared = new Item('Students', '/admin/students');
    $loop = new Group('Loop', new Item('A', '/a'));
    (new ReflectionProperty(Component::class, 'children'))->setValue($loop, new ComponentCollection($loop));

    expect(fn () => build(new Navigation($shared, new Group('Again', $shared))))->toThrow(InvalidNavigationException::class, 'two nodes are named "students"')
        ->and(fn () => build(new Navigation(new Item('A', '/a'), new Item(new Property(Item::Name, 'a'), new Property(Item::Label, 'B'), new Property(Item::Url, '/b')))))->toThrow(InvalidNavigationException::class)
        ->and(fn () => build(new Navigation($loop)))->toThrow(InvalidNavigationException::class, 'Circular');
});

it('reports duplicate names found while merging contributions', function (): void {
    expect(fn () => (new NavigationTreeBuilder)->build(new Navigation(new Item('Help', '/help')), 'navigation:test', 3, [[new Item('Help', '/other')]]))
        ->toThrow(DuplicateNavigationItemException::class, 'two nodes named "help" (at "#0.#0" and "+0.0")');
});

it('keeps dividers and headings stable and requirement metadata on nodes', function (): void {
    $tree = build(new Navigation(new Item('A', '/a'), new Divider, new Heading('Later'), new Item('B', '/b', new Permission('b.view')), new Divider));

    expect(array_map(static fn ($node): string => $node->id, $tree->nodes))->toBe(['a', build(new Navigation(new Item('A', '/a'), new Divider, new Heading('Later'), new Item('B', '/b', new Permission('b.view')), new Divider))->nodes[1]->id, 'later', 'b', $tree->nodes[4]->id])
        ->and($tree->nodes[1]->id)->toStartWith('divider-')
        ->and($tree->find('b')?->requirements[0])->toBeInstanceOf(Permission::class)
        ->and((new Item(new Property(Item::Label, 'C'), new Property(Item::Url, '/c'), new Property(Item::Permission, 'c.view')))->requirements()[0]->name)->toBe('c.view');
});

it('registers as the "navigation" kind and as a blueprint part', function (): void {
    $kind = new NavigationKind;
    $blueprint = new Blueprint('students', new Source(Student::class), new Ability('viewAny', Student::class), Definitions::admin());

    expect($kind->name(Definitions::admin()))->toBe('admin')
        ->and($kind->validate(Definitions::admin()))->toBe([])
        ->and($kind->metadata(Definitions::admin()))->toMatchArray(['label' => 'Administration', 'display' => 'sidebar'])
        ->and(implode(' ', $kind->validate(new Navigation(new Item('A', '/a')))))->toContain('needs a Name')
        ->and($blueprint->parts('navigation'))->toHaveCount(1)
        ->and((new Dropdown('Reports', new Item('A', '/a')))->isCollapsed())->toBeTrue();
});
