<?php

declare(strict_types=1);

namespace Gcego\Settings\Transfer;

/**
 * A dry run of an import. Each row has a status:
 *
 * - create:    no override here yet;
 * - update:    an override with another value exists (a conflict: import with overwrite to replace it);
 * - unchanged: the same value is already stored;
 * - refused:   unknown, sensitive, not allowed at this scope, not yours to change, or invalid (reason given).
 *
 * versions holds the stored versions the preview saw: the import refuses
 * to proceed if any of them changed in between.
 */
final readonly class ImportPreview
{
    /**
     * @param  list<array{key: string, label: string, status: string, reason: string|null}>  $rows
     * @param  array<string, mixed>  $values  Importable values by key.
     * @param  array<string, int>  $versions
     */
    public function __construct(public array $rows, public array $values, public array $versions) {}

    /**
     * @return list<string>
     */
    public function keysWith(string $status): array
    {
        return array_values(array_map(static fn (array $row): string => $row['key'], array_filter($this->rows, static fn (array $row): bool => $row['status'] === $status)));
    }

    public function hasRefusals(): bool
    {
        return $this->keysWith('refused') !== [];
    }

    public function hasConflicts(): bool
    {
        return $this->keysWith('update') !== [];
    }
}
