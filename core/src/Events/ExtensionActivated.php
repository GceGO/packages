<?php

declare(strict_types=1);

namespace Gcego\Core\Events;

final readonly class ExtensionActivated
{
    public function __construct(public string $id, public ?float $durationMs) {}
}
