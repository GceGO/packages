<?php

declare(strict_types=1);

namespace Gcego\Notifications\Livewire;

/**
 * Remembers (per request) that a toast host was rendered.
 */
final class ToastHostRegistry
{
    private bool $rendered = false;

    /**
     * True when a host was already rendered in this request; claims it otherwise.
     */
    public function claimed(): bool
    {
        $claimed = $this->rendered;
        $this->rendered = true;

        return $claimed;
    }
}
