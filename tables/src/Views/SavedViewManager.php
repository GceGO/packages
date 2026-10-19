<?php

declare(strict_types=1);

namespace Gcego\Tables\Views;

use Gcego\Tables\Contracts\SavedViewRepository;
use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Query\ResolvedTable;
use Gcego\Tables\State\TableState;
use Gcego\Tables\State\TableStateNormalizer;
use Gcego\Tables\Support\TableOwner;
use Gcego\Tables\Table;
use Gcego\Tables\TablesConfig;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * Saving and restoring table views for their owner.
 *
 * A view stores only user-editable state (search, filters, sorting, page
 * size, column layout), never scopes, permissions or record keys. Restoring
 * re-applies the current user's permissions and tenant: the state goes
 * through the lenient normalizer with today's column access (dropped parts
 * are reported), and the query runs through gcego/data with every scope.
 * Views of another user, tenant or table are not found; views saved by an
 * incompatible state version are refused.
 */
final readonly class SavedViewManager
{
    public const string NamePattern = '/^[^\x00-\x1F\x7F]{1,60}$/u';

    public function __construct(private SavedViewRepository $views, private TablesConfig $config, private ExceptionHandler $exceptions) {}

    public function enabled(ResolvedTable $table): bool
    {
        return $this->config->savedViews && $table->table->get(Table::SavedViews) !== false;
    }

    /**
     * @return list<SavedView>
     */
    public function all(ResolvedTable $table, ?TableOwner $owner): array
    {
        if ($owner === null || ! $this->enabled($table)) {
            return [];
        }

        try {
            return $this->views->all($owner, $table->identity());
        } catch (\Throwable $exception) {
            // Unreachable storage (for example, migrations not run) must not break the table.
            $this->exceptions->report($exception);

            return [];
        }
    }

    public function save(ResolvedTable $table, TableOwner $owner, string $name, TableState $state): SavedView
    {
        $name = $this->name($name);

        if ($this->views->nameTaken($owner, $table->identity(), $name)) {
            $existing = array_values(array_filter($this->views->all($owner, $table->identity()), static fn (SavedView $view): bool => $view->name === $name))[0] ?? null;

            if ($existing !== null) {
                return $this->views->update($owner, $table->identity(), $existing->id, state: $state->toView(), version: TableState::Version) ?? $existing;
            }
        }

        if ($this->views->count($owner, $table->identity()) >= $this->config->maxSavedViews) {
            throw InvalidTableStateException::invalid('views', sprintf('cannot exceed %d saved views.', $this->config->maxSavedViews));
        }

        return $this->views->create($owner, $table->identity(), $name, $state->toView(), TableState::Version);
    }

    /**
     * @return array{0: TableState, 1: list<string>}|null The restored state and what no longer applied, or null when not found or incompatible.
     */
    public function restore(ResolvedTable $table, TableOwner $owner, string $id, TableStateNormalizer $lenient): ?array
    {
        $view = $this->views->find($owner, $table->identity(), $id);

        if ($view === null || $view->version !== TableState::Version) {
            return null;
        }

        $state = $lenient->normalize(array_intersect_key($view->state, array_flip(['search', 'filters', 'sorts', 'per_page', 'order', 'hidden', 'density'])));

        return [$state, $lenient->dropped];
    }

    public function rename(ResolvedTable $table, TableOwner $owner, string $id, string $name): ?SavedView
    {
        $name = $this->name($name);

        if ($this->views->nameTaken($owner, $table->identity(), $name, except: $id)) {
            throw InvalidTableStateException::invalid('views.name', 'is already used by another view.');
        }

        return $this->views->update($owner, $table->identity(), $id, name: $name);
    }

    public function delete(ResolvedTable $table, TableOwner $owner, string $id): bool
    {
        return $this->views->delete($owner, $table->identity(), $id);
    }

    public function setDefault(ResolvedTable $table, TableOwner $owner, ?string $id): void
    {
        if ($id !== null && $this->views->find($owner, $table->identity(), $id) === null) {
            return;
        }

        $this->views->setDefault($owner, $table->identity(), $id);
    }

    public function default(ResolvedTable $table, ?TableOwner $owner): ?SavedView
    {
        foreach ($this->all($table, $owner) as $view) {
            if ($view->isDefault) {
                return $view;
            }
        }

        return null;
    }

    private function name(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if (preg_match(self::NamePattern, $name) !== 1) {
            throw InvalidTableStateException::invalid('views.name', 'must be 1 to 60 characters.');
        }

        return $name;
    }
}
