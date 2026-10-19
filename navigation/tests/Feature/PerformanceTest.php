<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\Tests\Fixtures\Providers\PendingRequestsBadge;
use Gcego\Registry\Registry;
use Gcego\Security\Permissions\Permission;
use Illuminate\Support\Facades\Gate;

/**
 * @return array{0: float, 1: mixed}
 */
function measured(Closure $callback): array
{
    $started = hrtime(true);
    $result = $callback();

    return [round((hrtime(true) - $started) / 1e6, 1), $result];
}

it('resolves large navigations with bounded checks, queries and markup', function (): void {
    $world = world();
    actAs($world['teacher']);
    $checks = 0;
    Gate::before(function () use (&$checks): bool {
        $checks++;

        return true;
    });

    $groups = [];

    for ($group = 1; $group <= 15; $group++) {
        $permission = new Permission('area'.$group.'.view');
        $items = [];

        for ($item = 1; $item <= 10; $item++) {
            $items[] = new Item(new Property(Item::Name, "g{$group}i{$item}"), new Property(Item::Label, "Item {$group}.{$item}"), new Property(Item::Url, "/admin/area/{$group}/{$item}"), ...($item === 1 && $group <= 10 ? [new Property(Item::Badge, PendingRequestsBadge::class), new Property(Item::BadgeTtl, 60)] : []));
        }

        $groups[] = new Group(new Property(Group::Name, 'g'.$group), new Property(Group::Label, 'Area '.$group), $permission, ...$items);
    }

    app(Registry::class)->register(new Navigation('large', ...$groups));
    onPage('/admin/area/7/3');

    [$treeMs] = measured(fn () => navigation()->tree('large'));
    $queries = queriesDuring(function () use (&$firstMs, &$html): void {
        [$firstMs, $html] = measured(fn (): string => navigation()->render('large')->toHtml());
    });
    $firstChecks = $checks;

    onPage('/admin/area/2/5');
    $cached = queriesDuring(function () use (&$secondMs): void {
        [$secondMs] = measured(fn (): string => navigation()->render('large')->toHtml());
    });

    fwrite(STDERR, sprintf(
        "\n[navigation perf] 15 groups x 10 items (165 nodes, 15 permissions, 10 badge providers): tree %.1f ms once; first render %.1f ms, %d authorization checks, %d queries, %d KB; next page %.1f ms, %d queries (badges cached)\n",
        $treeMs, $firstMs, $firstChecks, count($queries), strlen($html) / 1024, $secondMs, count($cached),
    ));

    // One check per group permission, and one policy check per badge query (gcego/data).
    expect($firstChecks)->toBeLessThanOrEqual(15 + 10)
        ->and(count($queries))->toBeLessThanOrEqual(10)
        ->and($cached)->toBe([])
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and($firstMs)->toBeLessThan(1500);
});
