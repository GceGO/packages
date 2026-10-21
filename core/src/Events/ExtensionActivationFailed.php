<?php

declare(strict_types=1);

namespace Gcego\Core\Events;

final readonly class ExtensionActivationFailed
{
    public function __construct(public string $id, public ?float $durationMs, public \Throwable $exception) {}
}
