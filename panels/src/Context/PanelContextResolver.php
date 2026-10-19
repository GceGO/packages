<?php

declare(strict_types=1);

namespace Gcego\Panels\Context;

use Gcego\Panels\Exceptions\PanelNotFoundException;

/**
 * Holds the current request's panel (container-scoped: reset per request
 * and per queue job / Octane request). Only the AuthorizePanel middleware
 * sets it, after every check passed; it runs on panel routes and, as
 * Livewire persistent middleware, on Livewire updates of their components.
 */
final class PanelContextResolver
{
    private ?PanelContext $context = null;

    public function set(PanelContext $context): void
    {
        $this->context = $context;
    }

    public function current(): ?PanelContext
    {
        return $this->context;
    }

    /**
     * @throws PanelNotFoundException
     */
    public function require(): PanelContext
    {
        return $this->context ?? throw new PanelNotFoundException('This request is not in a panel.');
    }

    public function clear(): void
    {
        $this->context = null;
    }
}
