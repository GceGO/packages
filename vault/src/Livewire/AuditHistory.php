<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Models\AuditEntry;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The access audit log of this scope (a tenant sees only its own entries):
 * filter by event, person and dates; export the filtered entries as CSV.
 */
final class AuditHistory extends VaultComponent
{
    use WithPagination;

    #[Url(except: '')]
    public string $event = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    protected function permission(): ?string
    {
        return VaultPermissions::AuditView;
    }

    public function updated(): void
    {
        $this->search = mb_substr($this->search, 0, 100);
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $query = $this->query();

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['when', 'event', 'actor', 'subject', 'target', 'scope', 'reason'], escape: '\\');

            $query->chunkById(500, static function ($entries) use ($out): void {
                foreach ($entries as $entry) {
                    // Cells starting with formula characters are prefixed, so spreadsheets never evaluate them.
                    fputcsv($out, array_map(static fn (?string $cell): string => preg_match('/^[=+\-@\t\r]/', (string) $cell) === 1 ? "'".$cell : (string) $cell, [
                        $entry->created_at?->toIso8601String(), $entry->event->value, $entry->actor_name, $entry->subject_name, $entry->target, $entry->tenant === '' ? 'global' : $entry->tenant, $entry->reason,
                    ]), escape: '\\');
                }
            });

            fclose($out);
        }, 'vault-audit-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        return view('gcego-vault::livewire.audit', [...$this->shared(), 'entries' => $this->query()->latest('id')->paginate(50), 'events' => AuditEvent::cases()]);
    }

    /**
     * @return Builder<AuditEntry>
     */
    private function query(): Builder
    {
        $term = trim($this->search);

        return AuditEntry::query()->where('tenant', $this->scope())
            ->when(AuditEvent::tryFrom($this->event) !== null, fn ($query) => $query->where('event', $this->event))
            ->when($term !== '', static fn ($query) => $query->where(static fn ($query) => $query->where('actor_name', 'like', '%'.$term.'%')->orWhere('subject_name', 'like', '%'.$term.'%')->orWhere('target', 'like', '%'.$term.'%')))
            ->when($this->validDate($this->from), fn ($query) => $query->where('created_at', '>=', Date::parse($this->from)->startOfDay()))
            ->when($this->validDate($this->to), fn ($query) => $query->where('created_at', '<=', Date::parse($this->to)->endOfDay()));
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false;
    }
}
