<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Registry;
use Gcego\Runtime\Exceptions\InvalidRenderContextException;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ExampleSearch;
use Gcego\Runtime\Tests\Fixtures\ExampleTable;

it('renders a minimal blueprint as a labelled section', function (): void {
    $output = html(app(Runtime::class)->render(new Blueprint('subjects'))->toHtml());

    expect($output)->toMatch('/^<section id="(gcego-[^"]+)" aria-labelledby="\1-title" data-gcego-blueprint="subjects"><header><h1 id="\1-title">Subjects<\/h1><\/header><\/section>$/');
});

it('renders metadata and parts in declaration order', function (): void {
    $blueprint = new Blueprint(
        'subjects',
        new Property(Blueprint::Title, 'Subject <Management>'),
        new Property(Blueprint::Description, 'Manage academic subjects'),
        new ExampleSearch('search'),
        new ExampleTable('table', new ExampleParagraph('Mathematics'), new ExampleParagraph('Physics')),
    );

    $output = html(app(Runtime::class)->render($blueprint)->toHtml());

    expect($output)->toContain('>Subject &lt;Management&gt;</h1><p>Manage academic subjects</p></header>')
        ->and(strpos($output, 'data-state-key="subjects:search"'))->toBeLessThan((int) strpos($output, '<table'))
        ->and($output)->toContain('<table data-path="subjects.table"><caption>table</caption><tbody><tr><td><p data-path="subjects.table.#0">Mathematics</p></td></tr><tr><td><p data-path="subjects.table.#1">Physics</p></td></tr></tbody></table>');
});

it('renders a blueprint without a table or form', function (): void {
    expect(app(Runtime::class)->render(new Blueprint('dashboard', new ExampleSearch('search')))->toHtml())->toContain('Search');
});

it('resolves registered blueprints by identity', function (): void {
    app(Registry::class)->register(new Blueprint('subjects', new ExampleTable('table')));

    $byIdentity = app(Runtime::class)->render('blueprint:subjects')->toHtml();
    $byName = app(Runtime::class)->render('subjects')->toHtml();

    expect($byIdentity)->toContain('data-gcego-blueprint="blueprint:subjects"')
        ->and($byName)->toBe($byIdentity)
        ->and(app(Runtime::class)->identityOf('subjects'))->toBe('blueprint:subjects');
});

it('reports missing and invalid blueprints', function (): void {
    expect(fn () => app(Runtime::class)->render('blueprint:missing'))->toThrow(DefinitionNotFoundException::class)
        ->and(fn () => app(Runtime::class)->render(new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b'))))
        ->toThrow(RenderException::class, 'Definition "blueprint" is invalid and cannot be rendered');
});

it('can skip validation when configured', function (): void {
    $this->rebuildApplication(['gcego-runtime.rendering.validate' => false]);

    expect(app(Runtime::class)->render(new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b')))->toHtml())->toContain('<table');
});

it('renders the same definition repeatedly with identical output', function (): void {
    $blueprint = new Blueprint('subjects', new ExampleTable('table', new ExampleParagraph('A')));

    expect(app(Runtime::class)->render($blueprint)->toHtml())->toBe(app(Runtime::class)->render($blueprint)->toHtml());
});

it('passes context options to renderers', function (): void {
    $output = app(Runtime::class)->render(new Blueprint('subjects'), headingLevel: 2)->toHtml();

    expect($output)->toContain('<h2 id=')
        ->and(fn () => app(Runtime::class)->render(new Blueprint('subjects'), panel: 'Not Valid'))->toThrow(InvalidRenderContextException::class);
});
