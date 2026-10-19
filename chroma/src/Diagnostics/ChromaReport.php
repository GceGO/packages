<?php

declare(strict_types=1);

namespace Gcego\Chroma\Diagnostics;

/**
 * Machine-readable output of ChromaInspector.
 */
final readonly class ChromaReport
{
    /**
     * @param  list<ChromaDiagnostic>  $diagnostics
     * @param  list<array{path: string, class: string, slots: array<string, string>}>  $resolved  Effective classes per slot of each inspected component.
     */
    public function __construct(
        public array $diagnostics,
        public array $resolved = [],
    ) {}

    /**
     * @return list<ChromaDiagnostic>
     */
    public function check(string $check): array
    {
        return array_values(array_filter($this->diagnostics, static fn (ChromaDiagnostic $diagnostic): bool => $diagnostic->check === $check));
    }

    public function isHealthy(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->level === ChromaDiagnostic::Error) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'healthy' => $this->isHealthy(),
            'diagnostics' => array_map(static fn (ChromaDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
            'resolved' => $this->resolved,
        ];
    }
}
