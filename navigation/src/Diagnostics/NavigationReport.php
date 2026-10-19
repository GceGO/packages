<?php

declare(strict_types=1);

namespace Gcego\Navigation\Diagnostics;

/**
 * Output of NavigationInspector: the node tree (names, types, paths) and findings, never badge values.
 */
final readonly class NavigationReport
{
    /**
     * @param  list<array{id: string, path: string, type: string, depth: int, requirements: list<string>}>  $nodes
     * @param  list<NavigationDiagnostic>  $diagnostics
     */
    public function __construct(public ?string $identity, public array $nodes, public array $diagnostics) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<NavigationDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (NavigationDiagnostic $diagnostic): bool => $diagnostic->level === NavigationDiagnostic::Error));
    }

    /**
     * @return list<NavigationDiagnostic>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (NavigationDiagnostic $diagnostic): bool => $diagnostic->level === NavigationDiagnostic::Warning));
    }

    /**
     * The checks that reported something.
     *
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_map(static fn (NavigationDiagnostic $diagnostic): string => $diagnostic->check, $this->diagnostics)));
    }

    /**
     * @return array{identity: string|null, healthy: bool, nodes: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['identity' => $this->identity, 'healthy' => $this->isHealthy(), 'nodes' => $this->nodes, 'diagnostics' => array_map(static fn (NavigationDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics)];
    }
}
