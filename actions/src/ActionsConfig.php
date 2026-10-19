<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Exceptions\InvalidActionException;

/**
 * Validated gcego-actions configuration.
 */
final readonly class ActionsConfig
{
    /**
     * @param  array<string, bool>  $confirmation  Confirmation default by action type key (delete, force_delete, restore, bulk).
     */
    public function __construct(
        public array $confirmation = ['delete' => true, 'force_delete' => true, 'restore' => false, 'bulk' => true],
        public int $confirmationTtl = 600,
        public bool $transactions = true,
        public int $maxSelection = 500,
        public int $chunkSize = 100,
        public int $idempotencyTtl = 3600,
        public ?string $cacheStore = null,
    ) {
        foreach (['confirmation.ttl' => $confirmationTtl, 'bulk.max_selection' => $maxSelection, 'bulk.chunk_size' => $chunkSize, 'idempotency.ttl' => $idempotencyTtl] as $key => $value) {
            if ($value < 1) {
                throw InvalidActionException::invalid('configuration', sprintf('gcego-actions.%s must be a positive integer.', $key));
            }
        }

        if ($chunkSize > 100) {
            throw InvalidActionException::invalid('configuration', 'gcego-actions.bulk.chunk_size must not exceed 100 (gcego/data page size limit).');
        }
    }

    /**
     * @param  array<mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $int = static function (string $path, int $default) use ($config): int {
            $value = data_get($config, $path, $default);

            return is_int($value) ? $value : throw InvalidActionException::invalid('configuration', sprintf('gcego-actions.%s must be an integer.', $path));
        };
        $confirmation = ['delete' => true, 'force_delete' => true, 'restore' => false, 'bulk' => true];

        foreach (array_keys($confirmation) as $key) {
            $value = data_get($config, 'confirmation.'.$key, $confirmation[$key]);
            $confirmation[$key] = is_bool($value) ? $value : throw InvalidActionException::invalid('configuration', sprintf('gcego-actions.confirmation.%s must be a boolean.', $key));
        }

        $transactions = data_get($config, 'transactions.enabled', true);
        $store = data_get($config, 'cache.store');

        return new self(
            confirmation: $confirmation,
            confirmationTtl: $int('confirmation.ttl', 600),
            transactions: is_bool($transactions) ? $transactions : throw InvalidActionException::invalid('configuration', 'gcego-actions.transactions.enabled must be a boolean.'),
            maxSelection: $int('bulk.max_selection', 500),
            chunkSize: $int('bulk.chunk_size', 100),
            idempotencyTtl: $int('idempotency.ttl', 3600),
            cacheStore: $store === null || is_string($store) ? $store : throw InvalidActionException::invalid('configuration', 'gcego-actions.cache.store must be a store name or null.'),
        );
    }
}
