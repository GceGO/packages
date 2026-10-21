<?php

declare(strict_types=1);

namespace Gcego\Core\Events;

final readonly class FrameworkBootFailed
{
    public function __construct(public \Throwable $exception, public float $durationMs) {}
}
