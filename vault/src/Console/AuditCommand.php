<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Models\AuditEntry;
use Illuminate\Console\Command;

final class AuditCommand extends Command
{
    protected $signature = 'gcego:vault:audit {--event= : Only this event ("role.assigned")} {--tenant= : Only this scope} {--limit=50}';

    protected $description = 'Show the latest entries of the Vault access audit log';

    public function handle(): int
    {
        $entries = AuditEntry::query()
            ->when(is_string($this->option('event')), fn ($query) => $query->where('event', $this->option('event')))
            ->when(is_string($this->option('tenant')), fn ($query) => $query->where('tenant', $this->option('tenant')))
            ->latest('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->get();

        $this->table(['When', 'Event', 'Actor', 'Subject', 'Target', 'Scope', 'Reason'], $entries->map(static fn (AuditEntry $entry): array => [
            $entry->created_at?->toDateTimeString(), $entry->event->value, $entry->actor_name ?? ($entry->actor_id === null ? 'system' : $entry->actor_type.'#'.$entry->actor_id),
            $entry->subject_name ?? ($entry->subject_id === null ? '' : $entry->subject_type.'#'.$entry->subject_id), $entry->target, $entry->tenant === '' ? 'global' : $entry->tenant, $entry->reason,
        ])->all());

        return self::SUCCESS;
    }
}
