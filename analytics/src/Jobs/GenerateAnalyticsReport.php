<?php

declare(strict_types=1);

namespace Gcego\Analytics\Jobs;

use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Models\AnalyticsExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Generates a queued export (identifiers only: the owner, tenant and
 * report are re-read and re-authorized). Retried; a claim makes
 * concurrent deliveries of the same job a no-op.
 */
final class GenerateAnalyticsReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public readonly int $export) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(ExportManager $exports): void
    {
        $export = AnalyticsExport::query()->find($this->export);

        if ($export !== null) {
            $exports->generate($export);
        }
    }

    public function failed(Throwable $exception): void
    {
        AnalyticsExport::query()->whereKey($this->export)->whereIn('status', [AnalyticsExport::Pending, AnalyticsExport::Running])->update(['status' => AnalyticsExport::Failed, 'error' => class_basename($exception), 'updated_at' => now()]);
    }
}
