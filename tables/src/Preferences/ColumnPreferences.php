<?php

declare(strict_types=1);

namespace Gcego\Tables\Preferences;

use Gcego\Tables\Contracts\PreferenceStore;
use Gcego\Tables\Query\ResolvedTable;
use Gcego\Tables\State\TableState;
use Gcego\Tables\State\TableStateNormalizer;
use Gcego\Tables\Support\TableOwner;
use Gcego\Tables\Table;
use Gcego\Tables\TablesConfig;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

/**
 * Per-user (and per-tenant) column order, hidden columns, page size and density for one table.
 *
 * Stored values are untrusted: on restore they go through the lenient
 * normalizer with the user's current column access, so a column the user
 * may no longer read never comes back, and columns added or removed since
 * are handled. Storage failures never break the table (they are reported).
 */
final readonly class ColumnPreferences
{
    public const int Version = 1;

    public function __construct(private PreferenceStore $store, private TablesConfig $config, private ExceptionHandler $exceptions) {}

    /**
     * The stored layout as normalizer input (order, hidden, per_page, density), or [] when none.
     *
     * @return array<string, mixed>
     */
    public function load(ResolvedTable $table, ?TableOwner $owner, TableStateNormalizer $lenient): array
    {
        if (! $this->enabled($table) || $owner === null) {
            return [];
        }

        try {
            $stored = $this->store->get($owner, $table->identity());
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);

            return [];
        }

        if ($stored === null || $stored['version'] !== self::Version) {
            return [];
        }

        $state = $lenient->normalize(array_intersect_key($stored['state'], array_flip(['order', 'hidden', 'per_page', 'density'])));

        return $state->toPreferences();
    }

    public function save(ResolvedTable $table, ?TableOwner $owner, TableState $state): void
    {
        if (! $this->enabled($table) || $owner === null) {
            return;
        }

        try {
            $this->store->put($owner, $table->identity(), $state->toPreferences(), self::Version);
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
        }
    }

    public function reset(ResolvedTable $table, ?TableOwner $owner): void
    {
        if ($owner === null) {
            return;
        }

        try {
            $this->store->forget($owner, $table->identity());
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
        }
    }

    public function enabled(ResolvedTable $table): bool
    {
        return $this->config->preferences && $table->table->get(Table::ColumnManager) !== false;
    }
}
