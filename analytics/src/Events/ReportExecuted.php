<?php

declare(strict_types=1);

namespace Gcego\Analytics\Events;

final readonly class ReportExecuted
{
    public function __construct(public string $report, public ?string $tenant, public int $rows, public bool $cached, public float $durationMs, public string $correlation) {}
}
