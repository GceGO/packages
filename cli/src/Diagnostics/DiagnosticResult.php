<?php

declare(strict_types=1);

namespace Gcego\Cli\Diagnostics;

/**
 * The outcome of one gcego:doctor check.
 */
final readonly class DiagnosticResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $id,
        public string $label,
        public DiagnosticStatus $status,
        public string $message,
        public ?string $remediation = null,
        public ?string $package = null,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function pass(string $id, string $label, string $message, ?string $package = null, array $details = []): self
    {
        return new self($id, $label, DiagnosticStatus::Pass, $message, null, $package, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function warning(string $id, string $label, string $message, ?string $remediation, ?string $package = null, array $details = []): self
    {
        return new self($id, $label, DiagnosticStatus::Warning, $message, $remediation, $package, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function failure(string $id, string $label, string $message, ?string $remediation, ?string $package = null, array $details = []): self
    {
        return new self($id, $label, DiagnosticStatus::Failure, $message, $remediation, $package, $details);
    }

    public static function skipped(string $id, string $label, string $message, ?string $package = null): self
    {
        return new self($id, $label, DiagnosticStatus::Skipped, $message, null, $package);
    }

    public static function notApplicable(string $id, string $label, string $message, ?string $package = null): self
    {
        return new self($id, $label, DiagnosticStatus::NotApplicable, $message, null, $package);
    }

    /**
     * @return array{id: string, label: string, status: string, message: string, remediation: string|null, package: string|null, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'status' => $this->status->value, 'message' => $this->message, 'remediation' => $this->remediation, 'package' => $this->package, 'details' => $this->details];
    }
}
