<?php

declare(strict_types=1);

namespace Gcego\Testing\Diagnostics;

use Gcego\Testing\Compatibility\CompatibilityResult;

final readonly class TestingReport
{
    /**
     * @param  list<TestingDiagnostic>  $diagnostics
     * @param  list<CompatibilityResult>  $packages
     */
    public function __construct(public array $diagnostics, public array $packages = []) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<TestingDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (TestingDiagnostic $diagnostic): bool => $diagnostic->level === TestingDiagnostic::Error));
    }

    public function check(string $check): ?TestingDiagnostic
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->check === $check) {
                return $diagnostic;
            }
        }

        return null;
    }

    /**
     * @return array{healthy: bool, diagnostics: list<array{check: string, level: string, message: string}>, packages: list<array{name: string, status: string, version: ?string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'healthy' => $this->isHealthy(),
            'diagnostics' => array_map(static fn (TestingDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
            'packages' => array_map(static fn (CompatibilityResult $result): array => $result->toArray(), $this->packages),
        ];
    }
}
