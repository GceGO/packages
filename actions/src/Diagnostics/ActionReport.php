<?php

declare(strict_types=1);

namespace Gcego\Actions\Diagnostics;

/**
 * Output of ActionInspector: per-action summaries and findings.
 */
final readonly class ActionReport
{
    /**
     * @param  list<array<string, scalar|list<string>|null>>  $actions
     * @param  list<ActionDiagnostic>  $diagnostics
     */
    public function __construct(public array $actions, public array $diagnostics) {}

    public function isHealthy(): bool
    {
        return array_filter($this->diagnostics, static fn (ActionDiagnostic $diagnostic): bool => $diagnostic->level === ActionDiagnostic::Error) === [];
    }

    /**
     * @return list<ActionDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (ActionDiagnostic $diagnostic): bool => $diagnostic->level === ActionDiagnostic::Error));
    }

    /**
     * @return array{healthy: bool, actions: list<array<string, scalar|list<string>|null>>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['healthy' => $this->isHealthy(), 'actions' => $this->actions, 'diagnostics' => array_map(static fn (ActionDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics)];
    }
}
