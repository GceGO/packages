<?php

declare(strict_types=1);

namespace Gcego\Studio\Bootstrap;

/**
 * The outcome of Studio's bootstrap verification.
 */
final readonly class BootstrapResult
{
    /**
     * @param  list<string>  $problems
     * @param  list<string>  $extensions
     */
    public function __construct(
        public bool $ok,
        public array $problems,
        public int $packages,
        public array $extensions,
        public string $kernel,
        public float $durationMs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['ok' => $this->ok, 'problems' => $this->problems, 'packages' => $this->packages, 'extensions' => $this->extensions, 'kernel' => $this->kernel, 'duration_ms' => $this->durationMs];
    }
}
