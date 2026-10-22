<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Diagnostics\RenderingInspector;
use Gcego\Rendering\Exceptions\RendererNotFoundException;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Rendering through gcego/runtime: renderer resolution, rendered markup
 * and failures. render() uses Rendering::render() (no authorization), and
 * renderAuthorized() the same path as <x-gcego-rendering::render>.
 */
final class RuntimeTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container)
    {
        parent::__construct($verifier);
    }

    /**
     * @param  array<string, scalar>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     */
    public function render(ComponentInterface|string $target, RenderMode $mode = RenderMode::Static, ?string $panel = null, ?string $theme = null, int $headingLevel = 1, array $metadata = [], array $state = []): HtmlTestable
    {
        $html = Rendering::render($target, $mode, null, $panel, $theme, $headingLevel, $metadata, $state)->toHtml();

        return new HtmlTestable($this->verifier, $html, 'rendered component');
    }

    public function renderAuthorized(string $identifier, ?string $panel = null, ?string $theme = null): HtmlTestable
    {
        return new HtmlTestable($this->verifier, Rendering::renderAuthorized($identifier, panel: $panel, theme: $theme)->toHtml(), 'rendered definition');
    }

    /**
     * The component (or class) resolves to this renderer class.
     *
     * @param  class-string  $renderer
     */
    public function assertRendererFor(ComponentInterface $component, string $renderer): self
    {
        $actual = null;
        $exception = $this->caught(function () use ($component, &$actual): void {
            $actual = $this->container->make(RendererRegistry::class)->for($component)::class;
        });

        return $this->check($actual === $renderer, sprintf('assertRendererFor(%s, %s)', $component::class, $renderer), sprintf('Expected [%s] to render with [%s].', $component::class, $renderer), ['renderer' => $renderer], ['renderer' => $actual ?? $this->describe($exception)], 'Register it with RendererRegistry::register().');
    }

    /**
     * Some component in the tree has no renderer; with $class, that one.
     */
    public function assertMissingRenderer(ComponentInterface $tree, ?string $class = null): self
    {
        $missing = $this->container->make(RenderingInspector::class)->inspect($tree)->missingRenderers;
        $classes = array_map(static fn (array $entry): string => $entry['class'], $missing);

        return $this->check($class === null ? $classes !== [] : in_array($class, $classes, true), sprintf('assertMissingRenderer(%s, %s)', $tree::class, $this->export($class)), 'Expected the tree to contain a component without a renderer.', ['missing' => $class ?? '≥ 1'], ['missing' => $classes]);
    }

    public function assertRenderable(ComponentInterface $tree): self
    {
        $missing = $this->container->make(RenderingInspector::class)->inspect($tree)->missingRenderers;

        return $this->check($missing === [], 'assertRenderable('.$tree::class.')', 'Expected every component in the tree to have a renderer.', ['missing' => []], ['missing' => array_map(static fn (array $entry): string => $entry['path'].' ('.$entry['class'].')', $missing)]);
    }

    /**
     * Rendering fails with an exception of $exception (found anywhere in the
     * previous() chain, since Blade wraps view errors).
     *
     * @param  class-string<Throwable>  $exception
     */
    public function assertRenderFails(ComponentInterface|string $target, string $exception = RendererNotFoundException::class, ?string $message = null): self
    {
        $thrown = $this->caught(fn () => Rendering::render($target)->toHtml());
        $match = $this->verifier->rootOf($thrown, $exception);

        return $this->check($match !== null && ($message === null || str_contains($match->getMessage(), $message)), sprintf('assertRenderFails(%s, %s)', is_string($target) ? $target : $target::class, $exception), sprintf('Expected rendering to fail with [%s].', $exception), ['exception' => $exception, 'message' => $message], ['exception' => $this->describe($thrown)]);
    }

    public function assertHealthy(ComponentInterface|string|null $target = null): self
    {
        $problems = Reports::problems($this->container->make(RenderingInspector::class)->inspect($target));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the runtime inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'runtime';
    }
}
