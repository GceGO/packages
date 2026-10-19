<?php

declare(strict_types=1);

namespace Gcego\Widgets\Rendering;

use Closure;

/**
 * The frame of the widget being rendered, request-scoped (a scoped binding), so
 * nothing leaks between requests, users or widgets rendered together.
 */
final class WidgetRenderContext
{
    /**
     * @var list<WidgetRenderFrame>
     */
    private array $frames = [];

    public function current(): ?WidgetRenderFrame
    {
        return $this->frames === [] ? null : $this->frames[array_key_last($this->frames)];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(WidgetRenderFrame $frame, Closure $callback): mixed
    {
        $this->frames[] = $frame;

        try {
            return $callback();
        } finally {
            array_pop($this->frames);
        }
    }
}
