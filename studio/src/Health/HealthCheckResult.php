<?php

declare(strict_types=1);

namespace Gcego\Studio\Health;

/**
 * The outcome of one health check: what was checked, the status, an
 * explanation, how to fix it and which package it concerns. Details are
 * scalars; secrets are never included.
 */
final readonly class HealthCheckResult
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public function __construct(
        public string $id,
        public string $label,
        public HealthStatus $status,
        public string $message,
        public ?string $remediation = null,
        public ?string $package = null,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function pass(string $id, string $label, string $message, ?string $package = null, array $details = []): self
    {
        return new self($id, $label, HealthStatus::Pass, $message, null, $package, $details);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function warning(string $id, string $label, string $message, string $remediation, ?string $package = null, array $details = []): self
    {
        return new self($id, $label, HealthStatus::Warning, $message, $remediation, $package, $details);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function failure(string $id, string $label, string $message, string $remediation, ?string $package = null, array $details = []): self
    {
        return new self($id, $label, HealthStatus::Failure, $message, $remediation, $package, $details);
    }

    public static function skipped(string $id, string $label, string $message, ?string $package = null): self
    {
        return new self($id, $label, HealthStatus::Skipped, $message, null, $package);
    }

    /**
     * @return array{id: string, label: string, status: string, message: string, remediation: string|null, package: string|null, details: array<string, scalar|list<scalar>|null>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'status' => $this->status->value, 'message' => $this->message, 'remediation' => $this->remediation, 'package' => $this->package, 'details' => $this->details];
    }
}
