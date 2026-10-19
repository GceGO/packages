<?php

declare(strict_types=1);

namespace Gcego\Analytics\Events;

/**
 * Identifiers and counts only (no data, filters or recipients' identities).
 */
final readonly class ExportCompleted
{
    public function __construct(public string $export, public string $report, public ?string $tenant, public int $rows) {}
}
