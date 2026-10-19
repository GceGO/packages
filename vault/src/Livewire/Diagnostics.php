<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Panels\PanelManager;
use Gcego\Vault\Authorization\Explanation;
use Gcego\Vault\Diagnostics\VaultInspector;
use Gcego\Vault\Livewire\Concerns\FindsPeople;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Throwable;

/**
 * Permission diagnostics: why a person is allowed or denied a permission
 * here (sources, scope, super-administrator rule), and the health of the
 * installation. Only holders of vault.diagnostics.view see explanations.
 */
final class Diagnostics extends VaultComponent
{
    use FindsPeople;

    public string $personSearch = '';

    public ?string $personRef = null;

    public string $check = '';

    public string $panel = '';

    protected function permission(): ?string
    {
        return VaultPermissions::DiagnosticsView;
    }

    public function pick(string $reference): void
    {
        $this->personRef = $this->reference($this->person($reference));
        $this->personSearch = '';
    }

    public function render(): View
    {
        $person = $this->personRef === null ? null : $this->person($this->personRef);
        $explanation = $person !== null && trim($this->check) !== '' ? $this->vault()->explain($person, trim($this->check), $this->scope(), $this->panel === '' ? null : $this->panel) : null;

        return view('gcego-vault::livewire.diagnostics', [
            ...$this->shared(),
            'person' => $person,
            'results' => $this->personSearch === '' ? [] : $this->searchPeople($this->personSearch),
            'explanation' => $explanation,
            'report' => app(VaultInspector::class)->report($this->scope()),
            'permissionNames' => Permission::query()->orderBy('name')->pluck('name')->all(),
            'panels' => $this->panels(),
            'allowedReasons' => [Explanation::Granted, Explanation::SuperAdministrator],
        ]);
    }

    /**
     * @return list<string>
     */
    private function panels(): array
    {
        try {
            return app(PanelManager::class)->names();
        } catch (Throwable) {
            return [];
        }
    }
}
