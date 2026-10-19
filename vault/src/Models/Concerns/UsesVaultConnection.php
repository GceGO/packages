<?php

declare(strict_types=1);

namespace Gcego\Vault\Models\Concerns;

/**
 * Vault models use gcego-vault.connection (null: the default connection).
 */
trait UsesVaultConnection
{
    public function getConnectionName(): ?string
    {
        $connection = config('gcego-vault.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }
}
