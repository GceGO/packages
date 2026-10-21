<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

use Gcego\Core\Diagnostics\DiagnosticRedactor;
use Gcego\Core\Diagnostics\InfrastructureIssues;
use Gcego\Core\Events\ExtensionActivated;
use Gcego\Core\Events\ExtensionActivating;
use Gcego\Core\Events\ExtensionActivationFailed;
use Gcego\Core\Events\FrameworkBootFailed;
use Gcego\Core\Events\PackageBooted;
use Gcego\Core\Events\PackageBootFailed;
use Gcego\Core\Events\PackageBooting;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

/** Observers never change startup outcomes. Stores timings, never event objects. */
final class StartupTelemetry
{
    /** @var list<StartupTiming> */
    private array $timings = [];

    public function __construct(private readonly ?Dispatcher $events = null, private readonly ?LoggerInterface $logger = null, private readonly InfrastructureIssues $issues = new InfrastructureIssues) {}

    private bool $profiling = false;

    /** @var array<string, array{status: string, attempts: int, exception: string|null}> */
    private array $steps = [];

    /** @var array<string, float> */
    private array $phases = [];

    private ?float $durationMs = null;

    public function beginAttempt(): void
    {
        $this->phases = [];
        $this->durationMs = null;
    }

    public function configure(bool $profiling): void
    {
        $this->profiling = $profiling;
    }

    public function profiling(): bool
    {
        return $this->profiling;
    }

    public function finishAttempt(float $durationMs): void
    {
        $this->durationMs = $durationMs;
    }

    public function durationMs(): ?float
    {
        return $this->durationMs;
    }

    /** @return array<string, array{status: string, attempts: int, exception: string|null}> */
    public function steps(): array
    {
        return $this->steps;
    }

    /** @return array<string, float> */
    public function phases(): array
    {
        return $this->phases;
    }

    /** @template T
     * @param  callable(): T  $operation
     * @return T
     */
    public function phase(string $name, callable $operation): mixed
    {
        $started = hrtime(true);
        try {
            return $operation();
        } finally {
            $this->phases[$name] = round((hrtime(true) - $started) / 1e6, 3);
        }
    }

    public function publish(object $event): void
    {
        if ($event instanceof FrameworkBootFailed || $event instanceof PackageBootFailed || $event instanceof ExtensionActivationFailed) {
            $this->log('GCEGO initialization failed.', ['event' => $event::class, 'exception_class' => DiagnosticRedactor::exceptionClass($event->exception)]);
        }

        try {
            $this->events?->dispatch($event);
        } catch (Throwable $exception) {
            $this->issues->record('startup.observer_failed', exception: $exception);
            $this->log('A GCEGO startup observer failed.', ['event' => $event::class, 'exception_class' => DiagnosticRedactor::exceptionClass($exception)]);
        }
    }

    /** @param 'package'|'extension' $kind
     * @param  callable(): void  $initialize
     */
    public function measure(string $kind, string $id, callable $initialize): void
    {
        $key = $kind.':'.$id;
        $attempts = ($this->steps[$key]['attempts'] ?? 0) + 1;
        $this->steps[$key] = ['status' => 'booting', 'attempts' => $attempts, 'exception' => null];
        $this->publish($kind === 'package' ? new PackageBooting($id) : new ExtensionActivating($id));
        $started = $this->profiling ? hrtime(true) : null;

        try {
            $initialize();
        } catch (Throwable $exception) {
            $this->steps[$key] = ['status' => 'failed', 'attempts' => $attempts, 'exception' => DiagnosticRedactor::exceptionClass($exception)];
            $duration = $this->record($kind, $id, $started, false);
            $this->publish($kind === 'package'
                ? new PackageBootFailed($id, $duration, $exception)
                : new ExtensionActivationFailed($id, $duration, $exception));

            throw $exception;
        }

        $this->steps[$key] = ['status' => 'booted', 'attempts' => $attempts, 'exception' => null];
        $duration = $this->record($kind, $id, $started, true);
        $this->publish($kind === 'package' ? new PackageBooted($id, $duration) : new ExtensionActivated($id, $duration));
    }

    /** @return list<StartupTiming> Includes failed attempts and successful retries in execution order. */
    public function timings(): array
    {
        return $this->timings;
    }

    /** @param 'package'|'extension' $kind */
    private function record(string $kind, string $id, int|float|null $started, bool $succeeded): ?float
    {
        if ($started === null) {
            return null;
        }
        $duration = round((hrtime(true) - $started) / 1e6, 3);
        $this->timings[] = new StartupTiming($kind, $id, $duration, $succeeded);

        return $duration;
    }

    /** @param array<string, string> $context */
    private function log(string $message, array $context): void
    {
        try {
            $this->logger?->warning($message, $context);
        } catch (Throwable) {
            // Observability must not replace the original initialization outcome.
        }
    }
}
