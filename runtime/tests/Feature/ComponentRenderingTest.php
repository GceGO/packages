<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Exceptions\RendererNotFoundException;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\Styling\StyleSlots;
use Gcego\Runtime\Tests\Fixtures\CyclicComponent;
use Gcego\Runtime\Tests\Fixtures\ExampleContainer;
use Gcego\Runtime\Tests\Fixtures\ExampleHeading;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ExampleStyle;
use Gcego\Runtime\Tests\Fixtures\FailingComponent;
use Gcego\Runtime\Tests\Fixtures\FixtureStylingResolver;
use Gcego\Runtime\Tests\Fixtures\ListContainerRenderer;
use Gcego\Runtime\Tests\Fixtures\MissingViewComponent;
use Gcego\Runtime\Tests\Fixtures\TrustedHtmlComponent;
use Gcego\Runtime\Tests\Fixtures\UnrenderedComponent;

it('renders a simple component through its Blade view', function (): void {
    expect(html(app(Runtime::class)->render(new ExampleParagraph('Hello'))->toHtml()))->toBe('<p data-path="#0">Hello</p>');
});

it('renders nested components in order where the parent places them', function (): void {
    $tree = new ExampleContainer(
        'outer',
        new ExampleHeading('Overview'),
        new ExampleParagraph('First'),
        new ExampleContainer('inner', new ExampleParagraph('Nested')),
        new ExampleParagraph('Last'),
    );

    expect(html(app(Runtime::class)->render($tree)->toHtml()))->toContain(
        '<ul><li><h1 id="'
    )->toContain(
        '>Overview</h1></li><li><p data-path="outer.#1">First</p></li><li><div data-path="outer.inner"><ul><li><p data-path="outer.inner.#0">Nested</p></li></ul></div></li><li><p data-path="outer.#3">Last</p></li></ul>'
    );
});

it('renders the generic component with an accessible heading structure', function (): void {
    $output = html(app(Runtime::class)->render(new Component(
        new Property(Component::Name, 'dashboard'),
        new Property(Component::Label, 'Dashboard'),
        new Component(new Property(Component::Label, 'Statistics'), new ExampleHeading('Totals')),
        new ExampleParagraph('Welcome'),
    ))->toHtml());

    expect($output)->toMatch('/^<section id="(gcego-[^"]+)" aria-labelledby="\1-title"><h1 id="\1-title">Dashboard<\/h1>/')
        ->and($output)->toContain('>Statistics</h2>')
        ->and($output)->toContain('>Totals</h3>')
        ->and($output)->toContain('<p data-path="dashboard.#1">Welcome</p>');
});

it('never renders configuration nodes', function (): void {
    $output = app(Runtime::class)->render(new ExampleContainer('box', new ExampleStyle, new ExampleParagraph('Visible')))->toHtml();

    expect($output)->toContain('Visible')
        ->and($output)->not->toContain('NEVER-RENDERED');
});

it('escapes property values', function (): void {
    $output = app(Runtime::class)->render(new ExampleContainer(
        'box',
        new ExampleHeading('<script>alert("x")</script>'),
        new ExampleParagraph('<img src=x onerror=alert(1)>'),
    ))->toHtml();

    expect($output)->not->toContain('<script>')
        ->and($output)->not->toContain('<img')
        ->and($output)->toContain('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;')
        ->and($output)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('emits raw markup only through trusted results', function (): void {
    expect(app(Runtime::class)->render(new TrustedHtmlComponent('x'))->toHtml())->toBe('<strong>trusted markup</strong>');
});

it('reports missing renderers with type and path', function (): void {
    app(Runtime::class)->render(new ExampleContainer('box', new UnrenderedComponent('ghost')))->toHtml();
})->throws(RendererNotFoundException::class, 'No renderer is registered for '.UnrenderedComponent::class.' (type "fixtures.unrendered") at "box.ghost"');

it('wraps renderer failures with type, path and renderer', function (): void {
    app(Runtime::class)->render(new ExampleContainer('box', new FailingComponent('broken')))->toHtml();
})->throws(RenderException::class, 'Rendering component "fixtures.failing" at "box.broken" with Gcego\Runtime\Tests\Fixtures\FailingRenderer failed');

it('reports missing views', function (): void {
    app(Runtime::class)->render(new MissingViewComponent('x'))->toHtml();
})->throws(RenderException::class, 'View [fixtures::does-not-exist] used by Gcego\Runtime\Tests\Fixtures\MissingViewRenderer does not exist.');

it('stops cycles and runaway depth', function (): void {
    $this->app->make(RendererRegistry::class)->register(CyclicComponent::class, ListContainerRenderer::class);

    expect(fn () => app(Runtime::class)->render(new CyclicComponent)->toHtml())->toThrow(RenderException::class, 'Circular rendering detected');

    $deep = new ExampleParagraph('leaf');

    for ($level = 0; $level < 70; $level++) {
        $deep = new ExampleContainer('n'.$level, $deep);
    }

    expect(fn () => app(Runtime::class)->render($deep)->toHtml())->toThrow(RenderException::class, 'maximum depth of 64');
});

it('isolates failures in non-strict mode without leaking details in production', function (): void {
    $this->rebuildApplication(['gcego-runtime.rendering.strict' => false, 'app.debug' => false]);

    $output = html(app(Runtime::class)->render(new ExampleContainer('box', new FailingComponent('broken'), new UnrenderedComponent('ghost'), new ExampleParagraph('Still here')))->toHtml());

    expect($output)->toContain('<div id="gcego-')
        ->and($output)->toContain('role="alert" data-gcego-error="box.broken"><p>This section could not be displayed.</p></div>')
        ->and($output)->toContain('data-gcego-error="box.ghost"')
        ->and($output)->toContain('Still here')
        ->and($output)->not->toContain('password');
});

it('shows failure details only when debugging', function (): void {
    $this->rebuildApplication(['gcego-runtime.rendering.strict' => false, 'app.debug' => true]);

    expect(app(Runtime::class)->render(new FailingComponent('broken'))->toHtml())->toContain('<summary>Details</summary>');
});

it('resolves styling through the StylingResolver contract and escapes it', function (): void {
    app()->instance(StylingResolver::class, new FixtureStylingResolver);
    app()->forgetInstance(ListContainerRenderer::class);
    $this->rebuildApplicationWithStyling();

    $output = app(Runtime::class)->render(new ExampleContainer('box', new ExampleHeading('Title')))->toHtml();

    expect($output)->toContain('<div data-path="box" class="rounded-2xl border bg-white shadow-sm">')
        ->and($output)->toContain('class="rounded-2xl border bg-white shadow-sm">Title</h1>');

    $slots = new StyleSlots(new FixtureStylingResolver, new ExampleParagraph('x'), RenderContext::root(new ExampleParagraph('x')));

    expect((string) $slots->attribute('evil'))->toBe(' class="&quot; onmouseover=&quot;alert(1)"')
        ->and((string) $slots->attribute('missing'))->toBe('');
});
