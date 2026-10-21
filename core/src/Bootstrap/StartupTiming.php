<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

final readonly class StartupTiming
{
    /** @param 'package'|'extension' $kind */
    public function __construct(public string $kind, public string $id, public float $durationMs, public bool $succeeded) {}

    /** @return array{kind: string, id: string, durationMs: float, succeeded: bool} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'id' => $this->id,
            'durationMs' => $this->durationMs,
            'succeeded' => $this->succeeded,
        ];
    }
}
