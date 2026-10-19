<?php

declare(strict_types=1);

namespace Gcego\Tables\Rendering;

use Closure;

/**
 * The frame of the table being rendered, request-scoped (a scoped binding),
 * so nothing leaks between requests, users or tables rendered together.
 */
final class TableRenderContext
{
    /**
     * @var list<TableRenderFrame>
     */
    private array $frames = [];

    public function current(): ?TableRenderFrame
    {
        return $this->frames === [] ? null : $this->frames[array_key_last($this->frames)];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(TableRenderFrame $frame, Closure $callback): mixed
    {
        $this->frames[] = $frame;

        try {
            return $callback();
        } finally {
            array_pop($this->frames);
        }
    }
}
