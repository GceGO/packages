<?php

declare(strict_types=1);

namespace Gcego\Data\Support;

use Closure;
use Gcego\Components\Contracts\RuntimeValue;

/**
 * A trusted, server-side callback stored as a component property value.
 *
 * As a RuntimeValue it is normalized to a placeholder that carries only this
 * class name, so the closure never reaches registry manifests, Livewire
 * snapshots or diagnostics.
 */
final readonly class Callback implements RuntimeValue
{
    public function __construct(public Closure $closure) {}

    public function __invoke(mixed ...$arguments): mixed
    {
        return ($this->closure)(...$arguments);
    }
}
