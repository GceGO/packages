<?php

declare(strict_types=1);

namespace Gcego\Layouts\Diagnostics;

/**
 * Output of LayoutInspector: the component tree (types and paths) and findings.
 */
final readonly class LayoutReport
{
    /**
     * @param  list<array{path: string, type: string, class: class-string, depth: int, children: int, slots: list<string>}>  $nodes
     * @param  list<LayoutDiagnostic>  $diagnostics
     */
    public function __construct(public ?string $identity, public array $nodes, public array $diagnostics) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (LayoutDiagnostic $diagnostic): bool => $diagnostic->level === LayoutDiagnostic::Error));
    }

    /**
     * @return list<LayoutDiagnostic>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (LayoutDiagnostic $diagnostic): bool => $diagnostic->level === LayoutDiagnostic::Warning));
    }

    /**
     * The checks that reported something.
     *
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_map(static fn (LayoutDiagnostic $diagnostic): string => $diagnostic->check, $this->diagnostics)));
    }

    /**
     * @return array{identity: string|null, healthy: bool, nodes: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['identity' => $this->identity, 'healthy' => $this->isHealthy(), 'nodes' => $this->nodes, 'diagnostics' => array_map(static fn (LayoutDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics)];
    }
}
