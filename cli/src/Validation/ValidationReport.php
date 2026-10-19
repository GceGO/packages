<?php

declare(strict_types=1);

namespace Gcego\Cli\Validation;

final readonly class ValidationReport
{
    public const int SchemaVersion = 1;

    /**
     * @param  list<ValidationEntry>  $entries
     * @param  list<string>  $notices
     */
    public function __construct(public array $entries, public float $durationMs = 0.0, public array $notices = []) {}

    public function valid(): bool
    {
        return $this->invalid() === [];
    }

    /**
     * @return list<ValidationEntry>
     */
    public function invalid(): array
    {
        return array_values(array_filter($this->entries, static fn (ValidationEntry $entry): bool => ! $entry->valid()));
    }

    /**
     * @return array{schema: int, valid: bool, summary: array{definitions: int, valid: int, invalid: int}, entries: list<array<string, mixed>>, notices: list<string>}
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SchemaVersion,
            'valid' => $this->valid(),
            'summary' => ['definitions' => count($this->entries), 'valid' => count($this->entries) - count($this->invalid()), 'invalid' => count($this->invalid())],
            'entries' => array_map(static fn (ValidationEntry $entry): array => $entry->toArray(), $this->entries),
            'notices' => $this->notices,
        ];
    }
}
