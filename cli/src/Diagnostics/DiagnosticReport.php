<?php

declare(strict_types=1);

namespace Gcego\Cli\Diagnostics;

final readonly class DiagnosticReport
{
    /**
     * @param  list<DiagnosticResult>  $results
     */
    public function __construct(public array $results) {}

    public function healthy(): bool
    {
        return $this->count(DiagnosticStatus::Failure) === 0;
    }

    public function count(DiagnosticStatus $status): int
    {
        return count(array_filter($this->results, static fn (DiagnosticResult $result): bool => $result->status === $status));
    }

    public function result(string $id): ?DiagnosticResult
    {
        foreach ($this->results as $result) {
            if ($result->id === $id) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array{healthy: bool, summary: array<string, int>, checks: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $summary = [];

        foreach (DiagnosticStatus::cases() as $status) {
            $summary[$status->value] = $this->count($status);
        }

        return ['healthy' => $this->healthy(), 'summary' => $summary, 'checks' => array_map(static fn (DiagnosticResult $result): array => $result->toArray(), $this->results)];
    }
}
