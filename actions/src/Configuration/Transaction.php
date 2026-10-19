<?php

declare(strict_types=1);

namespace Gcego\Actions\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;

/**
 * Transaction behavior of an action:
 *
 *     new Transaction(connection: 'reporting')
 *     new Transaction(enabled: false)     // e.g. a handler that only calls an external API
 *
 * Without it, the record's or model's connection is used and
 * gcego-actions.transactions.enabled decides.
 */
final readonly class Transaction implements ConfigurationNode
{
    public function __construct(
        public bool $enabled = true,
        public ?string $connection = null,
        public int $attempts = 1,
    ) {}

    /**
     * @return array{enabled: bool, connection: string|null, attempts: int}
     */
    public function normalize(): array
    {
        return ['enabled' => $this->enabled, 'connection' => $this->connection, 'attempts' => $this->attempts];
    }
}
