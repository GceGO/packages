<?php

declare(strict_types=1);

namespace Gcego\Analytics\Events;

/**
 * Identifiers and counts only (no data, filters or recipients' identities).
 */
final readonly class ExportStarted
{
    public function __construct(public string $export, public string $report, public string $format, public ?string $tenant, public int $rows, public string $correlation) {}
}
