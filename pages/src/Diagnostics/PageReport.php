<?php

declare(strict_types=1);

namespace Gcego\Pages\Diagnostics;

/**
 * Output of PageInspector: the page's identity, type, route, components and
 * findings. Built from definitions and route tables only: no page was
 * opened, no record read, no query run.
 */
final readonly class PageReport
{
    /**
     * @param  array{path: string, name: string, registered: list<string>, record: bool, record_key: string|null, middleware: list<string>}  $route
     * @param  list<array{path: string, type: string, depth: int, requirements: list<string>}>  $components
     * @param  list<PageDiagnostic>  $diagnostics
     */
    public function __construct(
        public ?string $identity,
        public string $type,
        public array $route,
        public array $components,
        public array $diagnostics,
    ) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<PageDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (PageDiagnostic $diagnostic): bool => $diagnostic->level === PageDiagnostic::Error));
    }

    /**
     * @return list<PageDiagnostic>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (PageDiagnostic $diagnostic): bool => $diagnostic->level === PageDiagnostic::Warning));
    }

    /**
     * The checks that reported something.
     *
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_map(static fn (PageDiagnostic $diagnostic): string => $diagnostic->check, $this->diagnostics)));
    }

    /**
     * @return array{identity: string|null, type: string, healthy: bool, route: array<string, mixed>, components: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'identity' => $this->identity,
            'type' => $this->type,
            'healthy' => $this->isHealthy(),
            'route' => $this->route,
            'components' => $this->components,
            'diagnostics' => array_map(static fn (PageDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
