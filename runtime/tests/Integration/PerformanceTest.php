<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\Tests\Fixtures\ExampleContainer;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ExampleTable;

it('renders hundreds of nested components', function (): void {
    $sections = [];

    for ($section = 0; $section < 20; $section++) {
        $paragraphs = array_map(fn (int $index): ExampleParagraph => new ExampleParagraph('Item '.$index), range(1, 25));
        $sections[] = new ExampleContainer('s'.$section, ...$paragraphs);
    }

    $tree = new ExampleContainer('root', ...$sections);
    $start = hrtime(true);
    $html = app(Runtime::class)->render($tree)->toHtml();
    $elapsedMs = (hrtime(true) - $start) / 1e6;

    // Regression guard, not a benchmark (see benchmarks/run.php).
    expect(substr_count($html, '<p '))->toBe(500)
        ->and($elapsedMs)->toBeLessThan(5000.0);
});

it('keeps memory stable across repeated renders', function (): void {
    $blueprint = new Blueprint('subjects', new ExampleTable('table', ...array_map(fn (int $index): ExampleParagraph => new ExampleParagraph('Row '.$index), range(1, 50))));
    $runtime = app(Runtime::class);
    $runtime->render($blueprint)->toHtml();
    gc_collect_cycles();
    $before = memory_get_usage();

    for ($iteration = 0; $iteration < 50; $iteration++) {
        $runtime->render($blueprint)->toHtml();
    }

    gc_collect_cycles();

    expect(memory_get_usage() - $before)->toBeLessThan(2 * 1024 * 1024);
});

it('looks renderers up from a memoized map', function (): void {
    $registry = app(RendererRegistry::class);
    $paragraph = new ExampleParagraph('x');
    $start = hrtime(true);

    for ($lookup = 0; $lookup < 20000; $lookup++) {
        $registry->for($paragraph);
    }

    expect((hrtime(true) - $start) / 1e6)->toBeLessThan(1000.0);
});
