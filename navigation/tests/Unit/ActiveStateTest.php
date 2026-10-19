<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Navigation\Badges\NavigationBadge;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Exceptions\InvalidNavigationException;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\Resolution\ActiveStateResolver;

it('matches route-name patterns by segment, never by substring', function (string $pattern, string $route, bool $matches): void {
    expect(ActiveStateResolver::routeMatches($pattern, $route))->toBe($matches);
})->with([
    ['admin.subjects.*', 'admin.subjects.index', true],
    ['admin.subjects.*', 'admin.subjects.show', true],
    ['admin.subjects.*', 'admin.subjects', true],
    ['admin.subjects.*', 'admin.subjects.topics.edit', true],
    ['admin.subject.*', 'admin.subjects.index', false],
    ['admin.subjects.*', 'admin.subjectsx.index', false],
    ['admin.*.index', 'admin.students.index', true],
    ['admin.*.index', 'admin.students.show', false],
    ['admin.students.index', 'admin.students.index', true],
    ['admin.students.index', 'admin.students.indexer', false],
]);

it('matches path patterns by segment', function (string $pattern, string $path, bool $matches): void {
    expect(ActiveStateResolver::pathMatches($pattern, $path))->toBe($matches);
})->with([
    ['/admin/subjects/*', '/admin/subjects', true],
    ['/admin/subjects/*', '/admin/subjects/3/edit', true],
    ['/admin/subjects/*', '/admin/subjectsx', false],
    ['/admin/subjects/{subject}', '/admin/subjects/3', true],
    ['/admin/subjects/{subject}', '/admin/subjects/3/edit', false],
    ['/admin', '/admin/', true],
    ['/admin', '/administration', false],
    ['/', '/', true],
]);

it('normalizes paths and refuses other hosts', function (): void {
    expect(ActiveStateResolver::pathOf('/admin/students/', 'localhost'))->toBe('/admin/students')
        ->and(ActiveStateResolver::pathOf('http://localhost/admin?x=1', 'localhost'))->toBe('/admin')
        ->and(ActiveStateResolver::pathOf('https://evil.example.com/admin', 'localhost'))->toBeNull()
        ->and(ActiveStateResolver::pathOf('mailto:a@b.c', 'localhost'))->toBeNull();
});

it('builds badges with accessible meaning and bounded text', function (): void {
    expect(NavigationBadge::count(0))->toBeNull()
        ->and(NavigationBadge::count(150, 'pending requests')?->text)->toBe('99+')
        ->and(NavigationBadge::count(3, 'pending requests')?->label)->toBe('3 pending requests')
        ->and((new NavigationBadge('New', 'success'))->classes())->toContain('text-gcego-success')
        ->and(NavigationBadge::fromArray((new NavigationBadge('7', 'danger', 'overdue'))->toArray())?->toArray())->toBe(['text' => '7', 'variant' => 'danger', 'label' => 'overdue', 'icon' => null])
        ->and(fn () => new NavigationBadge('New', 'neon'))->toThrow(InvalidNavigationException::class, 'variant "neon"')
        ->and(fn () => new NavigationBadge(str_repeat('x', 13)))->toThrow(InvalidNavigationException::class, '1 to 12 characters');
});

it('validates its configuration', function (array $config, string $message): void {
    expect(fn () => NavigationConfig::fromArray($config))->toThrow(InvalidNavigationException::class, $message);
})->with([
    'visibility' => [['visibility' => ['default' => 'everyone']], 'visibility.default'],
    'depth' => [['groups' => ['max_depth' => 0]], 'max_depth'],
    'bool' => [['links' => ['absolute' => 'yes']], 'links.absolute'],
    'contributors' => [['contributors' => [Item::class]], 'is not a'],
    'contributor type' => [['contributors' => [1]], 'list of class names'],
]);

it('reads item link metadata', function (): void {
    $item = new Item(new Property(Item::Label, 'Members'), new Property(Item::Route, 'admin.members'), new Property(Item::Parameters, ['institution' => ':tenant', 'tab' => 'all']), new Property(Item::Active, ['admin.members.*', '/admin/people/*']));

    expect($item->parameters())->toBe(['institution' => ':tenant', 'tab' => 'all'])
        ->and($item->activePatterns())->toBe(['admin.members.*', '/admin/people/*'])
        ->and(NavigationConfig::fromArray([])->defaultVisibility)->toBe('authenticated');
});

it('takes a typed Luminaries icon for a badge', function (): void {
    expect((new NavigationBadge('New', 'success', icon: Heroicon::Sparkles))->toArray()['icon'])->toBe('heroicon:sparkles');
});
