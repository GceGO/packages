<?php

declare(strict_types=1);

namespace Gcego\Forms\Rendering;

use Closure;

/**
 * The frame of the form being rendered, request-scoped (a scoped binding), so
 * nothing leaks between requests, users or concurrently hosted forms.
 */
final class FormRenderContext
{
    /**
     * @var list<FormRenderFrame>
     */
    private array $frames = [];

    public function current(): ?FormRenderFrame
    {
        return $this->frames === [] ? null : $this->frames[array_key_last($this->frames)];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(FormRenderFrame $frame, Closure $callback): mixed
    {
        $this->frames[] = $frame;

        try {
            return $callback();
        } finally {
            array_pop($this->frames);
        }
    }
}
