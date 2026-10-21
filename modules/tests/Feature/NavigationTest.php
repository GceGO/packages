<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationManager;
use Gcego\Registry\Contracts\RegistryInterface;

beforeEach(function (): void {
    $this->reboot(['gcego-pages.rendering.navigation' => 'admin']);
    app(RegistryInterface::class)->register(new Navigation(new Property(Navigation::Name, 'admin')));
    $this->world = world();
});

it('adds an opted-in module as a group of its listed resources and pages', function (): void {
    $tree = app(NavigationManager::class)->tree('admin');
    $group = $tree->find('module-academics');

    expect($group?->component->get('label'))->toBe('Academic Management')
        ->and(array_map(static fn ($node): mixed => $node->component->get('label'), $group->children ?? []))->toBe(['Subjects', 'Topics', 'Academic Overview']);
});

it('counts a resource\'s records as its badge only when it opts in', function (): void {
    [$subjects, $topics] = app(NavigationManager::class)->tree('admin')->find('module-academics')->children ?? [];

    expect($subjects->component->get(Item::BadgeCount))->toBe(Subject::class)
        ->and($subjects->component->get(Item::BadgeVariant))->toBe('success')
        ->and($subjects->component->get(Item::BadgeLabel))->toBe('subjects')
        ->and($topics->component->get(Item::BadgeCount))->toBeNull()
        ->and($topics->component->get(Item::BadgeVariant))->toBeNull();
});

it('refuses a badge color that is not a badge variant', function (): void {
    expect((new Resource(new Source(Subject::class), new Property(Resource::NavigationBadgeVariant, 'purple')))->validate()->isValid())->toBeFalse()
        ->and((new Resource(new Source(Subject::class), new Property(Resource::NavigationBadgeVariant, 'warning')))->validate()->isValid())->toBeTrue();
});

it('shows items only to users who may open the page', function (): void {
    $reader = squish((string) asMember($this->world['reader'])->get('/admin/academics/overview')->getContent());
    $visitor = squish((string) asMember($this->world['visitor'])->get('/admin/academics/overview')->getContent());

    expect($reader)->toContain('href="/admin/subjects"')->toContain('href="/admin/topics"')->not->toContain('href="/admin/tags"')
        ->and($visitor)->not->toContain('href="/admin/subjects"')->toContain('href="/admin/academics/overview"');
});

it('marks the resource active on all its pages, relationship pages included', function (): void {
    $html = squish((string) asMember($this->world['admin'])->get('/admin/subjects/1/topics')->getContent());

    expect($html)->toMatch('/<a[^>]*href="\/admin\/subjects"[^>]*aria-current="page"|<a[^>]*aria-current="page"[^>]*href="\/admin\/subjects"/');
});

it('leaves modules out of the navigation unless they opt in or navigation.auto is on', function (): void {
    $this->reboot(['gcego-pages.rendering.navigation' => 'admin', 'gcego-modules.navigation.auto' => false]);
    app(RegistryInterface::class)->register(new Navigation(new Property(Navigation::Name, 'admin')));
    world();

    // "academics" opts in explicitly; "notes" does not (and has no list page anyway).
    $groups = array_map(static fn ($node): string => $node->id, app(NavigationManager::class)->tree('admin')->nodes);
    expect($groups)->toBe(['module-academics']);
});
