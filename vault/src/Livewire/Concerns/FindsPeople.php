<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire\Concerns;

use Gcego\Vault\Support\Context;
use Gcego\Vault\Support\HolderReference;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Searching the configured subject models (people) by name or email, and
 * resolving a picked person again on the server. In a tenant, only verified
 * members of that tenant are ever returned.
 */
trait FindsPeople
{
    /**
     * @return Builder<Model>|null
     */
    protected function people(): ?Builder
    {
        $class = app(VaultConfig::class)->subjects[0] ?? null;

        return $class === null ? null : $class::query();
    }

    /**
     * @return list<Model>
     */
    protected function searchPeople(string $term, int $limit = 8): array
    {
        $term = trim(mb_substr($term, 0, 100));
        $query = $this->people();

        if ($term === '' || $query === null) {
            return [];
        }

        $table = $query->getModel()->getTable();
        $columns = array_values(array_filter(['name', 'email'], static fn (string $column): bool => Schema::connection($query->getModel()->getConnectionName())->hasColumn($table, $column)));

        if ($columns === []) {
            return [];
        }

        $found = $query->where(static function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        })->limit($limit * 3)->get()->all();

        return array_slice(array_values(array_filter($found, fn (Model $person): bool => $this->visiblePerson($person))), 0, $limit);
    }

    protected function visiblePerson(Model $person): bool
    {
        return $this->scope() === '' || ($person instanceof Authenticatable && app(Context::class)->isMember($person, $this->scope()));
    }

    /**
     * A person by the reference the browser sent, visible in this scope (404 otherwise).
     */
    protected function person(string $reference): Model
    {
        $person = app(HolderReference::class)->decode($reference);

        return $person !== null && $this->visiblePerson($person) ? $person : abort(404);
    }

    public function reference(Model $person): string
    {
        return app(HolderReference::class)->encode($person) ?? '';
    }

    protected function displayName(Model $person): string
    {
        $name = $person->getAttribute('name');
        $email = $person->getAttribute('email');

        return is_string($name) && $name !== '' ? $name : (is_string($email) ? $email : '#'.$person->getKey());
    }
}
