<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Card;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Stack;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Layout;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Registry\Registry;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Security\Requirements\Ability;
use Gcego\Widgets\Statistics\Stat;

/**
 * @return array{0: float, 1: string} Milliseconds (median of five renders) and the HTML.
 */
function timed(Closure $render): array
{
    $times = [];
    $html = '';

    for ($run = 0; $run < 5; $run++) {
        $started = hrtime(true);
        $html = $render();
        $times[] = (hrtime(true) - $started) / 1e6;
    }

    sort($times);

    return [round($times[2], 1), $html];
}

it('renders large and deeply nested layouts with bounded markup', function (): void {
    $world = world();
    actAs($world['teacher']);

    $plain = static fn (int $i): Component => new Component(new Property(Component::Name, 'c'.$i), new Property(Component::Label, 'Item '.$i));
    $large = new Stack(...array_map(static fn (int $section): Section => new Section('Section '.$section, new Grid(new Columns(4), ...array_map(static fn (int $i): Component => $plain($section * 100 + $i), range(1, 24)))), range(1, 12)));
    $deep = new Card(new Property(Card::Label, 'Deep'), $plain(1));

    for ($level = 0; $level < 10; $level++) {
        $deep = new Stack($deep);
    }

    $baseline = new Component(...array_map(static fn (int $i): Component => $plain($i), range(1, 288)));
    [$largeMs, $largeHtml] = timed(fn (): string => render($large));
    [$baselineMs, $baselineHtml] = timed(fn (): string => squish(Rendering::render($baseline)->toHtml()));
    [$deepMs, $deepHtml] = timed(fn (): string => render($deep));

    app(Registry::class)->register(new Layout('stats', new Ability('viewAny', Student::class), new Grid(new Columns(4), ...array_map(static fn (int $i): Stat => new Stat(new Property(Stat::Name, 's'.$i), new Property(Stat::Label, 'Stat '.$i), new Property(Stat::Value, $i), new ColumnSpan($i % 5 === 0 ? 2 : 1)), range(1, 40)))));
    $queries = queriesDuring(fn () => layoutHtml('stats'));
    [$widgetsMs, $widgetsHtml] = timed(fn (): string => layoutHtml('stats'));

    $wrappers = substr_count($largeHtml, '<div class="min-w-0');
    $layoutBytes = strlen($largeHtml) - strlen($baselineHtml);

    fwrite(STDERR, sprintf(
        "\n[layouts perf] 12 sections x 24 items (325 components): %.1f ms, %d KB (plain runtime for the same 288 items: %.1f ms, %d KB); 10-level nesting: %.1f ms, %d bytes; 40 stat widgets in a grid: %.1f ms, %d queries, %d KB\n",
        $largeMs, strlen($largeHtml) / 1024, $baselineMs, strlen($baselineHtml) / 1024, $deepMs, strlen($deepHtml), $widgetsMs, count($queries), strlen($widgetsHtml) / 1024,
    ));

    expect($wrappers)->toBe(0)
        ->and(substr_count($deepHtml, '<div'))->toBeLessThanOrEqual(14)
        ->and($layoutBytes / 12)->toBeLessThan(1600)
        ->and(substr_count($widgetsHtml, '<div class="min-w-0'))->toBe(8)
        ->and($queries)->toBe([])
        ->and($largeMs)->toBeLessThan(2000);
});
