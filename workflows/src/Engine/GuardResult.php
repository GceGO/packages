<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

final readonly class GuardResult
{
    public function __construct(public bool $allowed, public ?string $reason = null) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason): self
    {
        return new self(false, mb_substr($reason, 0, 300));
    }
}
