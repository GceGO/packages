<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Livewire\Concerns\FindsPeople;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * People and the roles they hold here. Without a search it lists the people
 * who hold any access in this scope; a search covers everyone visible here
 * (in a tenant: its verified members only). Roles are loaded for the whole
 * page with two queries (no per-person queries).
 */
final class PeopleIndex extends VaultComponent
{
    use FindsPeople, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    protected function permission(): ?string
    {
        return VaultPermissions::UsersView;
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 100);
        $this->resetPage();
    }

    public function render(): View
    {
        $people = trim($this->search) === '' ? $this->holders() : $this->searched();
        $evaluator = $this->vault()->evaluator();
        $evaluator->preload($people->items(), $this->scope());
        $rows = [];

        foreach ($people->items() as $person) {
            $effective = $evaluator->effective($person, $this->scope());
            $rows[] = ['person' => $person, 'reference' => $this->reference($person), 'roles' => $effective->roles, 'count' => count($effective->names()), 'super' => $effective->super, 'temporary' => $effective->expiresAt];
        }

        return view('gcego-vault::livewire.people', [...$this->shared(), 'rows' => $rows, 'people' => $people]);
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    private function holders(): LengthAwarePaginator
    {
        $query = $this->people();

        if ($query === null) {
            return new LengthAwarePaginator([], 0, 25);
        }

        $type = $query->getModel()->getMorphClass();
        $ids = Assignment::query()->where('model_type', $type)->where('tenant', $this->scope())->active()->distinct()->pluck('model_id')
            ->merge(Grant::query()->where('model_type', $type)->where('tenant', $this->scope())->active()->distinct()->pluck('model_id'))
            ->unique()->values()->all();

        return $query->whereKey($ids)->orderBy($query->getModel()->getKeyName())->paginate(25);
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    private function searched(): LengthAwarePaginator
    {
        $found = $this->searchPeople($this->search, 50);

        return new LengthAwarePaginator($found, count($found), 50);
    }
}
