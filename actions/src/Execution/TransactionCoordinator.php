<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Laravel transactions, nothing more: Connection::transaction() (nested
 * calls become savepoints), rethrown failures, and Connection::afterCommit()
 * for side effects, which run only after the outermost commit (immediately
 * when no transaction is open, never after a rollback).
 */
final readonly class TransactionCoordinator
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(?string $connection, bool $enabled, Closure $callback, int $attempts = 1): mixed
    {
        if (! $enabled) {
            return $callback();
        }

        return $this->connections->connection($connection)->transaction($callback, max(1, $attempts));
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public function afterCommit(?string $connection, Closure $callback): void
    {
        $resolved = $this->connections->connection($connection);

        if ($resolved instanceof Connection) {
            $resolved->afterCommit($callback);

            return;
        }

        $callback();
    }
}
