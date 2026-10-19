<?php

declare(strict_types=1);

use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Layouts\Tests\Fixtures\Policies\Denying;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Widgets\Livewire\WidgetHost;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

/**
 * @return array<string, string> Livewire component name by id.
 */
function children(string $html): array
{
    preg_match_all('/wire:id="([^"]+)" wire:name="([^"]+)"/', $html, $matches);

    return array_combine($matches[1], $matches[2]);
}

it('renders a blueprint made of layouts in the Livewire host, mounting each child host once', function (): void {
    $host = Livewire::test(BlueprintHost::class, ['blueprint' => 'students']);
    $children = children($host->html());

    expect(array_count_values($children))->toMatchArray(['gcego-table' => 1, 'gcego-form' => 1])
        ->and($host->html())->toMatch('/x-data="\{ active: /')
        ->and($host->html())->toContain('wire:ignore.self');
});

it('keeps child components and their state across re-renders of the surrounding component', function (): void {
    $host = Livewire::test(BlueprintHost::class, ['blueprint' => 'students']);
    $before = squish($host->html());
    preg_match_all('/wire:key="([^"]+)"/', $before, $keys);

    $host->call('$refresh');
    $after = squish($host->html());
    preg_match_all('/wire:key="([^"]+)"/', $after, $keysAfter);

    // Layout elements and child hosts keep their keys (Livewire re-renders children as placeholders, so their own markup is left out).
    $stable = static fn (array $keys): array => array_values(array_filter($keys, static fn (string $key): bool => str_starts_with($key, 'gcego-')));
    preg_match_all('/wire:id="([^"]+)"/', $before, $ids);
    preg_match_all('/wire:id="([^"]+)"/', $after, $idsAfter);

    expect($stable($keysAfter[1]))->toBe($stable($keys[1]))
        ->and(count(array_unique($keys[1])))->toBe(count($keys[1]))
        ->and(count($idsAfter[1]))->toBeGreaterThanOrEqual(3)
        ->and(array_values(array_intersect($ids[1], $idsAfter[1])))->toBe(array_values(array_unique($idsAfter[1])));
});

it('hosts interactive widgets embedded in a registered layout by owner and path', function (): void {
    $host = Livewire::test(WidgetHost::class, ['widget' => 'layout:dashboard', 'path' => 'dashboard.trends.enrolment']);

    expect($host->html())->toContain('data-gcego-chart-config')
        ->and($host->get('path'))->toBe('dashboard.trends.enrolment');

    $host->set('range', 'last_month')->assertOk();

    Livewire::test(WidgetHost::class, ['widget' => 'layout:dashboard', 'path' => 'dashboard.overview'])->assertNotFound();
    Livewire::test(WidgetHost::class, ['widget' => 'layout:dashboard', 'path' => 'dashboard.overview.stats.boys'])->assertForbidden();
});

it('re-checks the layout requirements on every widget host request', function (): void {
    $host = Livewire::test(WidgetHost::class, ['widget' => 'layout:dashboard', 'path' => 'dashboard.trends.enrolment']);

    Gate::policy(Student::class, Denying::class);
    actAs($this->world['teacher']);

    $host->call('refresh')->assertForbidden();
});

it('keeps layout state out of the Livewire snapshot', function (): void {
    $snapshot = (string) json_encode(Livewire::test(BlueprintHost::class, ['blueprint' => 'students'])->snapshot);

    expect($snapshot)->not->toContain('Amina')
        ->and($snapshot)->not->toContain('active')
        ->and($snapshot)->not->toContain('Gcego\\\\Layouts');
});
