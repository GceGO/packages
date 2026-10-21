<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

/**
 * The immutable outcome of a successful (or deliberately skipped) framework boot.
 *
 * Failures are reported by BootstrapException, never by a result.
 */
final readonly class BootstrapResult
{
    /**
     * @param  list<StartupTiming>  $timings  Includes failed attempts from retries.
     * @param  list<string>  $packages  Booted packages, in boot order.
     * @param  list<string>  $extensions  Activated extensions, in activation order.
     * @param  array<string, string>  $skippedExtensions  Extension id => reason.
     */
    public function __construct(
        public BootStatus $status,
        public array $packages = [],
        public array $extensions = [],
        public array $skippedExtensions = [],
        public float $durationMs = 0.0,
        public array $timings = [],
    ) {}

    public static function disabled(): self
    {
        return new self(BootStatus::Disabled);
    }

    public function isBooted(): bool
    {
        return $this->status === BootStatus::Booted;
    }

    /**
     * @return array{status: string, packages: list<string>, extensions: list<string>, skippedExtensions: array<string, string>, durationMs: float, timings: list<array{kind: string, id: string, durationMs: float, succeeded: bool}>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'packages' => $this->packages,
            'extensions' => $this->extensions,
            'skippedExtensions' => $this->skippedExtensions,
            'durationMs' => $this->durationMs,
            'timings' => array_map(static fn (StartupTiming $timing): array => $timing->toArray(), $this->timings),
        ];
    }
}
