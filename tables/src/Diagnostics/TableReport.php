<?php

declare(strict_types=1);

namespace Gcego\Tables\Diagnostics;

/**
 * Output of TableInspector: structure and findings, never record values.
 */
final readonly class TableReport
{
    /**
     * @param  list<array<string, scalar|list<string>|null>>  $columns
     * @param  list<array<string, scalar|null>>  $filters
     * @param  array<string, list<string>>  $actions  placement => action names
     * @param  list<TableDiagnostic>  $diagnostics
     */
    public function __construct(
        public string $table,
        public ?string $definition,
        public array $columns,
        public array $filters,
        public array $actions,
        public array $diagnostics,
    ) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<TableDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (TableDiagnostic $diagnostic): bool => $diagnostic->level === TableDiagnostic::Error));
    }

    /**
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_map(static fn (TableDiagnostic $diagnostic): string => $diagnostic->check, $this->diagnostics)));
    }

    /**
     * @return array{table: string, definition: string|null, healthy: bool, columns: list<array<string, scalar|list<string>|null>>, filters: list<array<string, scalar|null>>, actions: array<string, list<string>>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'definition' => $this->definition,
            'healthy' => $this->isHealthy(),
            'columns' => $this->columns,
            'filters' => $this->filters,
            'actions' => $this->actions,
            'diagnostics' => array_map(static fn (TableDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
