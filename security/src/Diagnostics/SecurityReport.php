<?php

declare(strict_types=1);

namespace Gcego\Security\Diagnostics;

/**
 * Output of SecurityInspector. It names permissions and definitions:
 * expose it only to administrators (CLI, protected tooling).
 */
final readonly class SecurityReport
{
    /**
     * @param  list<SecurityDiagnostic>  $diagnostics
     */
    public function __construct(public array $diagnostics) {}

    /**
     * @return list<SecurityDiagnostic>
     */
    public function check(string $check): array
    {
        return array_values(array_filter($this->diagnostics, static fn (SecurityDiagnostic $diagnostic): bool => $diagnostic->check === $check));
    }

    public function isHealthy(): bool
    {
        return array_filter($this->diagnostics, static fn (SecurityDiagnostic $diagnostic): bool => $diagnostic->level === SecurityDiagnostic::Error) === [];
    }

    /**
     * @return array{healthy: bool, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['healthy' => $this->isHealthy(), 'diagnostics' => array_map(static fn (SecurityDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics)];
    }
}
