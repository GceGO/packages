<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

/**
 * The outcome of one diagnostic check.
 *
 * Details contain versions, identifiers and counts only, never configuration
 * values or exception messages that could include secrets.
 */
final readonly class DiagnosticResult implements \JsonSerializable
{
    public string $message;

    /** @var array<string, scalar|list<scalar>|null> */
    public array $details;

    /** @param array<string, scalar|list<scalar>|null> $details */
    public function __construct(
        public string $check,
        public DiagnosticStatus $status,
        string $message,
        array $details = [],
        public ?string $resolution = null,
        public ?string $package = null,
        public ?string $extension = null,
    ) {
        $this->message = DiagnosticRedactor::text($message);
        $this->details = DiagnosticRedactor::details($details);
    }

    public function severity(): string
    {
        return match ($this->status) {
            DiagnosticStatus::Passed => 'info',
            DiagnosticStatus::Warning => 'warning',
            DiagnosticStatus::Failed => 'error',
        };
    }

    public function recommendedResolution(): ?string
    {
        if ($this->status === DiagnosticStatus::Passed) {
            return null;
        }

        return DiagnosticRedactor::text($this->resolution ?? match (explode('.', $this->check)[0]) {
            'configuration' => 'Correct the published Core configuration and refresh the Laravel configuration cache.',
            'php', 'laravel' => 'Use the PHP and Laravel versions and extensions supported by Core.',
            'packages' => 'Correct package declarations before Core closes registration, then restart the application.',
            'extensions', 'capabilities' => 'Correct extension requirements or enable their providers before activation.',
            'performance' => 'Inspect profiled initialization steps and reduce work in startup hooks.',
            'security' => 'Disable production debug mode and authorize access before exposing detailed reports.',
            default => 'Inspect the related Core configuration and startup records, then restart after correcting the cause.',
        });
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function passed(string $check, string $message, array $details = [], ?string $resolution = null, ?string $package = null, ?string $extension = null): self
    {
        return new self($check, DiagnosticStatus::Passed, $message, $details, $resolution, $package, $extension);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function warning(string $check, string $message, array $details = [], ?string $resolution = null, ?string $package = null, ?string $extension = null): self
    {
        return new self($check, DiagnosticStatus::Warning, $message, $details, $resolution, $package, $extension);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function failed(string $check, string $message, array $details = [], ?string $resolution = null, ?string $package = null, ?string $extension = null): self
    {
        return new self($check, DiagnosticStatus::Failed, $message, $details, $resolution, $package, $extension);
    }

    public function isFailure(): bool
    {
        return $this->status === DiagnosticStatus::Failed;
    }

    /**
     * @return array{check: string, code: string, status: string, severity: string, message: string, details: array<string, scalar|list<scalar>|null>, resolution: string|null, package: string|null, extension: string|null}
     */
    public function toArray(): array
    {
        return [
            'check' => DiagnosticRedactor::text($this->check),
            'code' => DiagnosticRedactor::text($this->check),
            'severity' => $this->severity(),
            'resolution' => $this->recommendedResolution(),
            'package' => $this->package === null ? null : DiagnosticRedactor::text($this->package),
            'extension' => $this->extension === null ? null : DiagnosticRedactor::text($this->extension),
            'status' => $this->status->value,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }

    /**
     * @return array{check: string, code: string, status: string, severity: string, message: string, details: array<string, scalar|list<scalar>|null>, resolution: string|null, package: string|null, extension: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
