<?php

declare(strict_types=1);

namespace Gcego\Analytics\Events;

final readonly class MetricCalculated
{
    public function __construct(public string $metric, public ?string $tenant, public bool $cached, public float $durationMs, public string $correlation) {}
}
