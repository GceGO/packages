<?php

declare(strict_types=1);

namespace Gcego\Analytics\Events;

/**
 * A query was refused before execution (no data, no input values).
 */
final readonly class AnalyticsQueryRejected
{
    public function __construct(public string $dataset, public string $reason, public ?string $tenant, public string $correlation) {}
}
