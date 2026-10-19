<?php

declare(strict_types=1);

namespace Gcego\Analytics\Events;

final readonly class AnalyticsCacheFlushed
{
    public function __construct(public ?string $dataset) {}
}
