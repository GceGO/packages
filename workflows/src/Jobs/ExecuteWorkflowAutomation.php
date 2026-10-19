<?php

declare(strict_types=1);

namespace Gcego\Workflows\Jobs;

use Gcego\Workflows\Automation\AutomationRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Runs one automation of one instance (identifiers only: the handler is
 * looked up in the definition again, the tenant restored from the instance).
 */
final class ExecuteWorkflowAutomation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $instance,
        public readonly string $automation,
        public readonly string $origin,
        public readonly int $sequence,
        public readonly string $correlation,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(AutomationRunner $runner): void
    {
        $runner->run($this->instance, $this->automation, $this->origin, $this->sequence, $this->correlation, $this->tries);
    }

    public function failed(Throwable $exception): void
    {
        app(AutomationRunner::class)->failed($this->instance, $this->automation, $this->origin, $this->sequence, $this->correlation, $exception);
    }
}
