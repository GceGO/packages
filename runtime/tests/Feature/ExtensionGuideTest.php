<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ExampleTable;

it('produces the output documented in docs/extension-guide.md', function (): void {
    $blueprint = new Blueprint(
        'subjects',
        new ExampleTable('table', new ExampleParagraph('Mathematics'), new ExampleParagraph('Physics')),
    );

    $output = (string) preg_replace('/gcego-[a-z0-9-]+?(-title)?"/', 'gcego-…$1"', html(app(Runtime::class)->render($blueprint)->toHtml()));

    expect($output)->toBe('<section id="gcego-…" aria-labelledby="gcego-…-title" data-gcego-blueprint="subjects"><header><h1 id="gcego-…-title">Subjects</h1></header><div><table data-path="subjects.table"><caption>table</caption><tbody><tr><td><p data-path="subjects.table.#0">Mathematics</p></td></tr><tr><td><p data-path="subjects.table.#1">Physics</p></td></tr></tbody></table></div></section>');
});
