<?php

declare(strict_types=1);

namespace Gcego\Pages\Rendering;

use Closure;
use Gcego\Pages\Context\PageContext;

/**
 * The pages being rendered (a stack, so a page rendered inside another one
 * cannot see its context). Set by PageManager for the duration of a render
 * and always popped, so no context outlives its render.
 */
final class PageRenderContext
{
    /**
     * @var list<PageContext>
     */
    private array $stack = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $render
     * @return T
     */
    public function run(PageContext $context, Closure $render): mixed
    {
        $this->stack[] = $context;

        try {
            return $render();
        } finally {
            array_pop($this->stack);
        }
    }

    public function current(): ?PageContext
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }
}
