<?php

declare(strict_types=1);

namespace Gcego\Infolists\Rendering;

use Closure;

/**
 * The frame of the infolist being rendered, request-scoped (a scoped binding), so
 * nothing leaks between requests, users or infolists rendered together.
 */
final class InfolistRenderContext
{
    /**
     * @var list<InfolistRenderFrame>
     */
    private array $frames = [];

    public function current(): ?InfolistRenderFrame
    {
        return $this->frames === [] ? null : $this->frames[array_key_last($this->frames)];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(InfolistRenderFrame $frame, Closure $callback): mixed
    {
        $this->frames[] = $frame;

        try {
            return $callback();
        } finally {
            array_pop($this->frames);
        }
    }
}
