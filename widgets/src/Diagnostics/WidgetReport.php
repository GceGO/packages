<?php

declare(strict_types=1);

namespace Gcego\Widgets\Diagnostics;

/**
 * Output of WidgetInspector: structure and findings, never metric values.
 */
final readonly class WidgetReport
{
    /**
     * @param  list<array<string, scalar|null>>  $widgets  The widget and its children.
     * @param  list<WidgetDiagnostic>  $diagnostics
     */
    public function __construct(public string $widget, public ?string $identity, public array $widgets, public array $diagnostics) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<WidgetDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (WidgetDiagnostic $diagnostic): bool => $diagnostic->level === WidgetDiagnostic::Error));
    }

    /**
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_map(static fn (WidgetDiagnostic $diagnostic): string => $diagnostic->check, $this->diagnostics)));
    }

    /**
     * @return array{widget: string, identity: string|null, healthy: bool, widgets: list<array<string, scalar|null>>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['widget' => $this->widget, 'identity' => $this->identity, 'healthy' => $this->isHealthy(), 'widgets' => $this->widgets, 'diagnostics' => array_map(static fn (WidgetDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics)];
    }
}
