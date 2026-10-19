<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

/**
 * The outcome of a command: the instance afterwards and what happened.
 * A replayed idempotent command returns the original result ($replayed).
 */
final readonly class TransitionResult
{
    public function __construct(
        public string $instance,
        public string $transition,
        public string $from,
        public string $to,
        public string $status,
        public int $version,
        public string $correlation,
        public bool $replayed = false,
    ) {}

    /**
     * @return array{instance: string, transition: string, from: string, to: string, status: string, version: int, correlation: string}
     */
    public function toArray(): array
    {
        return ['instance' => $this->instance, 'transition' => $this->transition, 'from' => $this->from, 'to' => $this->to, 'status' => $this->status, 'version' => $this->version, 'correlation' => $this->correlation];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, bool $replayed = false): self
    {
        $string = static fn (string $key): string => is_string($data[$key] ?? null) ? $data[$key] : '';

        return new self($string('instance'), $string('transition'), $string('from'), $string('to'), $string('status'), is_int($data['version'] ?? null) ? $data['version'] : 0, $string('correlation'), $replayed);
    }
}
