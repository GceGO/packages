<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

/**
 * The outcome of one diagnostic check.
 *
 * Details contain versions, identifiers and counts only, never configuration
 * values or exception messages that could include secrets.
 */
final readonly class DiagnosticResult
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public function __construct(
        public string $check,
        public DiagnosticStatus $status,
        public string $message,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function passed(string $check, string $message, array $details = []): self
    {
        return new self($check, DiagnosticStatus::Passed, $message, $details);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function warning(string $check, string $message, array $details = []): self
    {
        return new self($check, DiagnosticStatus::Warning, $message, $details);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public static function failed(string $check, string $message, array $details = []): self
    {
        return new self($check, DiagnosticStatus::Failed, $message, $details);
    }

    public function isFailure(): bool
    {
        return $this->status === DiagnosticStatus::Failed;
    }

    /**
     * @return array{check: string, status: string, message: string, details: array<string, scalar|list<scalar>|null>}
     */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'status' => $this->status->value,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
