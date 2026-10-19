<?php

declare(strict_types=1);

namespace Gcego\Modules\Diagnostics;

/**
 * What the inspector found about one module: its resources and pages, and
 * diagnostics (errors make it unhealthy). Safe to show in tooling.
 */
final readonly class ModuleReport
{
    /**
     * @param  array<string, array{operations: list<string>, pages: list<string>, relations: list<string>, model: string|null}>  $resources
     * @param  list<string>  $pages  every page identity the module derives
     * @param  list<ModuleDiagnostic>  $diagnostics
     */
    public function __construct(
        public string $module,
        public array $resources,
        public array $pages,
        public array $diagnostics,
    ) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<ModuleDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (ModuleDiagnostic $diagnostic): bool => $diagnostic->level === ModuleDiagnostic::Error));
    }

    /**
     * @return list<ModuleDiagnostic>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (ModuleDiagnostic $diagnostic): bool => $diagnostic->level === ModuleDiagnostic::Warning));
    }

    /**
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_map(static fn (ModuleDiagnostic $diagnostic): string => $diagnostic->check, $this->diagnostics)));
    }

    /**
     * @return array{module: string, healthy: bool, resources: array<string, array{operations: list<string>, pages: list<string>, relations: list<string>, model: string|null}>, pages: list<string>, diagnostics: list<array{check: string, level: string, message: string, subject: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'healthy' => $this->isHealthy(),
            'resources' => $this->resources,
            'pages' => $this->pages,
            'diagnostics' => array_map(static fn (ModuleDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
