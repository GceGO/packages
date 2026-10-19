<?php

declare(strict_types=1);

namespace Gcego\Vault\Console\Concerns;

use Gcego\Vault\VaultConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds a holder by key or email in the configured subject models.
 */
trait FindsHolders
{
    protected function findHolder(string $identifier): ?Model
    {
        foreach (app(VaultConfig::class)->subjects as $class) {
            $query = $class::query();
            $found = str_contains($identifier, '@')
                ? $query->where('email', $identifier)->first()
                : $query->whereKey($identifier)->first();

            if ($found instanceof Model) {
                return $found;
            }
        }

        return null;
    }
}
