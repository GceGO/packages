<?php

declare(strict_types=1);

use Gcego\Testing\GcegoTest;
use Gcego\Testing\Support\Html;

it('asserts text, elements and attributes with CSS-like selectors', function (): void {
    $html = '<main><h1 id="title" class="page heading">Subjects &amp; topics</h1><a href="/subjects/1" data-role="row">Algebra</a><a href="/subjects/2">Draft</a></main>';

    GcegoTest::html($html)
        ->assertSee('Subjects & topics')
        ->assertSeeText('Algebra')
        ->assertDontSeeText('Southern')
        ->assertSeeInOrder(['Subjects', 'Algebra', 'Draft'])
        ->assertElement('a', 2)
        ->assertElement('h1.heading#title')
        ->assertElement('a[data-role="row"]', 1)
        ->assertElement('//main/a[2]')
        ->assertMissingElement('table')
        ->assertAttribute('a', 'href', '/subjects/2');
});

it('ignores hidden text when reading visible text', function (): void {
    expect(Html::parse('<p>Shown <span aria-hidden="true">icon</span><script>secret()</script></p>')->bodyText())->toBe('Shown');
});

it('passes accessible markup and names each violation', function (string $rule, string $html): void {
    GcegoTest::accessibility($html)->assertViolates($rule);
    expect(failure(fn () => GcegoTest::accessibility($html)->assertPasses($rule)))->toContain('['.$rule.']');
})->with([
    'unlabelled input' => ['form-label', '<input name="q">'],
    'image without alt' => ['image-alt', '<img src="a.png">'],
    'empty button' => ['button-name', '<button><svg></svg></button>'],
    'empty link' => ['link-name', '<a href="/x"></a>'],
    'skipped heading' => ['heading-order', '<h1>A</h1><h3>B</h3>'],
    'empty heading' => ['empty-heading', '<h2></h2>'],
    'table without headers' => ['table-headers', '<table><tr><td>1</td></tr></table>'],
    'unnamed dialog' => ['dialog-name', '<div role="dialog">Hi</div>'],
    'duplicate id' => ['duplicate-id', '<p id="a"></p><p id="a"></p>'],
    'broken reference' => ['aria-reference', '<input aria-label="x" aria-describedby="missing">'],
    'unassociated error' => ['error-association', '<input aria-label="Name" aria-invalid="true">'],
    'positive tabindex' => ['positive-tabindex', '<div tabindex="3">x</div>'],
    'focusable in aria-hidden' => ['aria-hidden-focus', '<div aria-hidden="true"><a href="/x">x</a></div>'],
    'unnamed landmarks' => ['landmark-name', '<nav><a href="/">a</a></nav><nav><a href="/">b</a></nav>'],
]);

it('accepts the accessible equivalents', function (): void {
    GcegoTest::accessibility(<<<'HTML'
        <nav aria-label="Main"><a href="/">Home</a></nav>
        <nav aria-label="Account"><a href="/me"><img src="me.png" alt="Profile"></a></nav>
        <h1>Subjects</h1><h2>List</h2>
        <label for="q">Search</label><input id="q" name="q">
        <label>Code <input name="code"></label>
        <input aria-label="Name" aria-invalid="true" aria-describedby="name-error"><p id="name-error">Required.</p>
        <button aria-label="Close"><svg aria-hidden="true"></svg></button>
        <table><thead><tr><th scope="col">Name</th></tr></thead><tbody><tr><td>Algebra</td></tr></tbody></table>
        <div role="dialog" aria-labelledby="dialog-title"><h2 id="dialog-title">Delete?</h2></div>
        <img src="decor.png" alt="">
        HTML)->assertAccessible();
});

it('lets a test skip a rule explicitly', function (): void {
    GcegoTest::accessibility('<h1>A</h1><h4>B</h4>')->assertAccessible(['heading-order']);
});

it('names elements like a browser: aria-labelledby first, then aria-label, label, content and title', function (): void {
    GcegoTest::accessibility('<span id="l">Delete subject</span><button aria-labelledby="l"></button>')->assertPasses('button-name');
    GcegoTest::accessibility('<a href="/x" title="Open"></a>')->assertPasses('link-name');
    GcegoTest::accessibility('<button aria-labelledby="missing"></button>')->assertViolates('button-name')->assertViolates('aria-reference');
});
