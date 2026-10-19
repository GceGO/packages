<?php

declare(strict_types=1);

namespace Gcego\Infolists\Diagnostics;

/**
 * Output of InfolistInspector: structure and findings, never record values.
 */
final readonly class InfolistReport
{
    /**
     * @param  list<array<string, scalar|list<string>|null>>  $entries
     * @param  list<string>  $relations  Relation paths that will be eager-loaded.
     * @param  list<InfolistDiagnostic>  $diagnostics
     */
    public function __construct(
        public string $infolist,
        public ?string $definition,
        public array $entries,
        public array $relations,
        public array $diagnostics,
    ) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<InfolistDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (InfolistDiagnostic $diagnostic): bool => $diagnostic->level === InfolistDiagnostic::Error));
    }

    /**
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_map(static fn (InfolistDiagnostic $diagnostic): string => $diagnostic->check, $this->diagnostics)));
    }

    /**
     * @return array{infolist: string, definition: string|null, healthy: bool, entries: list<array<string, scalar|list<string>|null>>, relations: list<string>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'infolist' => $this->infolist,
            'definition' => $this->definition,
            'healthy' => $this->isHealthy(),
            'entries' => $this->entries,
            'relations' => $this->relations,
            'diagnostics' => array_map(static fn (InfolistDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
