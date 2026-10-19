<?php

declare(strict_types=1);

namespace Gcego\Studio\Health;

/**
 * Results of a health run. Healthy: no failure (warnings allowed).
 */
final readonly class HealthReport
{
    /**
     * @param  list<HealthCheckResult>  $results
     */
    public function __construct(public array $results) {}

    public function healthy(): bool
    {
        return $this->count(HealthStatus::Failure) === 0;
    }

    public function count(HealthStatus $status): int
    {
        return count(array_filter($this->results, static fn (HealthCheckResult $result): bool => $result->status === $status));
    }

    public function result(string $id): ?HealthCheckResult
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

        foreach (HealthStatus::cases() as $status) {
            $summary[$status->value] = $this->count($status);
        }

        return ['healthy' => $this->healthy(), 'summary' => $summary, 'checks' => array_map(static fn (HealthCheckResult $result): array => $result->toArray(), $this->results)];
    }
}
