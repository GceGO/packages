<?php

declare(strict_types=1);

namespace Gcego\Media\Scanning;

use Gcego\Media\Definitions\ScanStatus;

final readonly class ScanResult
{
    public function __construct(public ScanStatus $status, public ?string $signature = null) {}

    public static function clean(): self
    {
        return new self(ScanStatus::Clean);
    }

    public static function infected(string $signature): self
    {
        return new self(ScanStatus::Infected, mb_substr($signature, 0, 120));
    }

    public static function error(): self
    {
        return new self(ScanStatus::Error);
    }
}
