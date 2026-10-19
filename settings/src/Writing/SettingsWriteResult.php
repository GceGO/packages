<?php

declare(strict_types=1);

namespace Gcego\Settings\Writing;

/**
 * What a write did: keys applied (with their new versions at the target),
 * keys left unchanged (same value already stored), keys waiting for
 * approval (with their request ULIDs), and, for partial writes only, the
 * keys refused with their errors.
 */
final readonly class SettingsWriteResult
{
    /**
     * @param  array<string, int>  $applied  Key => new version (0 after a reset).
     * @param  list<string>  $unchanged
     * @param  array<string, string>  $pending  Key => request ULID.
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public array $applied,
        public array $unchanged,
        public array $pending,
        public array $errors,
        public string $correlation,
    ) {}

    public function changed(): bool
    {
        return $this->applied !== [];
    }

    public function version(string $key): ?int
    {
        return $this->applied[$key] ?? null;
    }
}
