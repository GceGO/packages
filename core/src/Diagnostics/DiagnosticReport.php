<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An immutable collection of diagnostic results, ready for CLI or HTTP tooling.
 *
 * @implements IteratorAggregate<int, DiagnosticResult>
 */
final readonly class DiagnosticReport implements \JsonSerializable, Countable, IteratorAggregate
{
    /**
     * @var list<DiagnosticResult>
     */
    private array $results;

    public string $generatedAt;

    public function __construct(DiagnosticResult ...$results)
    {
        $this->results = array_values($results);
        $this->generatedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    /**
     * @return list<DiagnosticResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function result(string $check): ?DiagnosticResult
    {
        foreach ($this->results as $result) {
            if ($result->check === $check) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return list<DiagnosticResult>
     */
    public function failures(): array
    {
        return $this->withStatus(DiagnosticStatus::Failed);
    }

    /**
     * @return list<DiagnosticResult>
     */
    public function warnings(): array
    {
        return $this->withStatus(DiagnosticStatus::Warning);
    }

    public function hasFailures(): bool
    {
        return $this->failures() !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings() !== [];
    }

    public function isHealthy(): bool
    {
        return ! $this->hasFailures();
    }

    /**
     * @return list<array{check: string, code: string, status: string, severity: string, message: string, details: array<string, scalar|list<scalar>|null>, resolution: string|null, package: string|null, extension: string|null}>
     */
    public function toArray(): array
    {
        return array_map(static fn (DiagnosticResult $result): array => $result->toArray(), $this->results);
    }

    public function health(): string
    {
        return $this->hasFailures() ? 'critical' : ($this->hasWarnings() ? 'warning' : 'healthy');
    }

    /** Safe for public uptime endpoints: contains no component or environment metadata.
     * @return array{health: string, healthy: bool, generatedAt: string}
     */
    public function publicSummary(): array
    {
        return ['health' => $this->health(), 'healthy' => $this->isHealthy(), 'generatedAt' => $this->generatedAt];
    }

    /** Detailed report: authorize access before returning this from HTTP endpoints.
     * @return array{health: string, healthy: bool, generatedAt: string, checks: list<array{check: string, code: string, status: string, severity: string, message: string, details: array<string, scalar|list<scalar>|null>, resolution: string|null, package: string|null, extension: string|null}>}
     */
    public function toStructuredArray(): array
    {
        return [...$this->publicSummary(), 'checks' => $this->toArray()];
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * @return Traversable<int, DiagnosticResult>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    /**
     * @return list<DiagnosticResult>
     */
    private function withStatus(DiagnosticStatus $status): array
    {
        return array_values(array_filter($this->results, static fn (DiagnosticResult $result): bool => $result->status === $status));
    }

    /** Detailed report: authorize access before returning this from HTTP endpoints.
     * @return array{health: string, healthy: bool, generatedAt: string, checks: list<array{check: string, code: string, status: string, severity: string, message: string, details: array<string, scalar|list<scalar>|null>, resolution: string|null, package: string|null, extension: string|null}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toStructuredArray();
    }
}
