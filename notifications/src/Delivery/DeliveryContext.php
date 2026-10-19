<?php

declare(strict_types=1);

namespace Gcego\Notifications\Delivery;

use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * Where a notification belongs (decided by the sender's code, never by request input).
 */
final readonly class DeliveryContext
{
    public function __construct(
        public ?string $tenant = null,
        public ?string $panel = null,
        public ?string $idempotencyKey = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {}

    public function expired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= CarbonImmutable::now();
    }

    /**
     * @return array{tenant: string|null, panel: string|null, key: string|null, expires_at: string|null}
     */
    public function toArray(): array
    {
        return ['tenant' => $this->tenant, 'panel' => $this->panel, 'key' => $this->idempotencyKey, 'expires_at' => $this->expiresAt?->format(DATE_ATOM)];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): ?string => is_string($data[$key] ?? null) && $data[$key] !== '' ? $data[$key] : null;
        $expires = $string('expires_at');

        return new self($string('tenant'), $string('panel'), $string('key'), $expires === null ? null : new DateTimeImmutable($expires));
    }
}
