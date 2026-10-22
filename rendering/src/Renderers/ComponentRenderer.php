<?php

declare(strict_types=1);

namespace Gcego\Rendering\Renderers;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Exceptions\RendererNotFoundException;
use Gcego\Rendering\Exceptions\RenderException;
use Gcego\Rendering\Exceptions\RenderingException;
use Gcego\Rendering\Output\Children;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\RenderingConfig;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\View\Factory;
use Throwable;

/**
 * Renders one component occurrence: resolves its renderer, guards depth and
 * cycles, and puts an error boundary around the (lazy) output.
 *
 * Strict mode rethrows the innermost failure (so the message names the
 * component that actually failed). Non-strict mode reports it and renders an
 * accessible placeholder; the message is shown only when app.debug is on.
 */
final readonly class ComponentRenderer
{
    public function __construct(
        private RendererRegistry $registry,
        private RenderingConfig $config,
        private Factory $views,
        private ExceptionHandler $exceptions,
    ) {}

    /**
     * @throws RenderingException in strict mode.
     */
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if ($context->depth() > $this->config->maxDepth) {
            throw RenderException::tooDeep($context->path(), $this->config->maxDepth);
        }

        if ($context->hasAncestor($component)) {
            throw RenderException::cycle($context->path(), $component::class);
        }

        try {
            $renderer = $this->registry->for($component, $context->path());
        } catch (RendererNotFoundException $exception) {
            return $this->fail($exception, $context);
        }

        try {
            $result = $renderer->render($component, $context);
        } catch (Throwable $exception) {
            return $this->fail($this->wrap($exception, $component, $context, $renderer::class), $context);
        }

        return RenderResult::deferred(function () use ($result, $component, $context, $renderer): string {
            try {
                return $result->toHtml();
            } catch (Throwable $exception) {
                return $this->fail($this->wrap($exception, $component, $context, $renderer::class), $context)->toHtml();
            }
        });
    }

    /**
     * The renderable children of an occurrence, for renderers that place them.
     */
    public function children(RenderContext $context, ?int $headingLevel = null): Children
    {
        return new Children($context, $this, $headingLevel);
    }

    public function registry(): RendererRegistry
    {
        return $this->registry;
    }

    private function wrap(Throwable $exception, ComponentInterface $component, RenderContext $context, string $renderer): Throwable
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof RenderingException) {
                return $current;
            }
        }

        return RenderException::failed($component->type(), $context->path(), $renderer, $exception);
    }

    private function fail(Throwable $exception, RenderContext $context): RenderResult
    {
        if ($this->config->strict) {
            throw $exception;
        }

        $this->exceptions->report($exception);

        return RenderResult::view($this->views->make('gcego-rendering::errors.component', [
            'id' => $context->domId('error'),
            'path' => $context->path(),
            'message' => $this->config->debug ? $exception->getMessage() : null,
        ]));
    }
}
