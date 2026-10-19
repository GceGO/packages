<?php

declare(strict_types=1);

namespace Gcego\Forms\Diagnostics;

/**
 * Output of FormInspector.
 */
final readonly class FormReport
{
    /**
     * @param  list<array<string, scalar|list<string>|null>>  $fields
     * @param  list<FormDiagnostic>  $diagnostics
     */
    public function __construct(public string $form, public array $fields, public array $diagnostics) {}

    public function isHealthy(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<FormDiagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static fn (FormDiagnostic $diagnostic): bool => $diagnostic->level === FormDiagnostic::Error));
    }

    /**
     * @return array{form: string, healthy: bool, fields: list<array<string, scalar|list<string>|null>>, diagnostics: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['form' => $this->form, 'healthy' => $this->isHealthy(), 'fields' => $this->fields, 'diagnostics' => array_map(static fn (FormDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics)];
    }
}
