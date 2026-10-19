<?php

declare(strict_types=1);

namespace Gcego\Workflows\Automation;

use Gcego\Security\SecurityManager;
use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Engine\CommandRunner;
use Gcego\Workflows\Engine\Effects;
use Gcego\Workflows\Engine\TransitionExecutor;
use Gcego\Workflows\Engine\TransitionResult;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\History\EventType;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Workflow;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Executes automation as the "automation" system actor:
 *
 * - it runs once per scheduling (a command record marks it done; retries
 *   after success are skipped), and only while the instance is still in
 *   the state visit that scheduled it (else it is recorded as skipped);
 * - the handler runs outside the workflow transaction, in the instance's
 *   tenant (gcego/security actingAs), with no user;
 * - its result is recorded; a returned transition runs as the
 *   "automation" system actor (the transition must name it);
 * - a failure is reported, rethrown for the queue to retry, and recorded
 *   in the history once retries are exhausted: never silently dropped.
 */
final readonly class AutomationRunner
{
    public function __construct(
        private Container $container,
        private WorkflowDefinitions $definitions,
        private CommandRunner $runner,
        private TransitionExecutor $executor,
        private HistoryRecorder $history,
        private SecurityManager $security,
    ) {}

    public function run(int $instanceId, string $name, string $origin, int $sequence, string $correlation, int $tries = 1): void
    {
        $instance = WorkflowInstance::query()->find($instanceId);
        $key = $this->key($instanceId, $name, $origin, $sequence);

        if ($instance === null || $this->runner->hasRun('automation', $instance, SystemActor::named(SystemActor::Automation), $key)) {
            return;
        }

        $workflow = $this->definitions->forInstance($instance);
        $automation = $this->find($workflow, $name, $origin);
        // State automation belongs to one visit of its state; transition automation always runs, but only continues the visit it was scheduled in.
        $stale = $instance->state_sequence !== $sequence || ! $instance->isRunning();

        if ($automation === null || (str_starts_with($origin, 'state:') && $stale)) {
            $this->finish($instance, $key, $name, $origin, 'skipped', null, $correlation);

            return;
        }

        try {
            $handler = $this->container->make($automation->handler());
            $next = $this->security->actingAs(null, $instance->tenant, static fn (): ?string => $handler instanceof WorkflowHandler ? $handler->handle(new WorkflowContext($instance, $workflow, SystemActor::named(SystemActor::Automation))) : null);
        } catch (Throwable $exception) {
            report($exception);

            // Run right after a commit (not queued): record the failure now instead of failing the committed request.
            if ($tries <= 1) {
                $this->failed($instanceId, $name, $origin, $sequence, $correlation, $exception);

                return;
            }

            throw $exception;
        }

        $this->finish($instance, $key, $name, $origin, 'completed', $stale ? null : $next, $correlation);
    }

    public function failed(int $instanceId, string $name, string $origin, int $sequence, string $correlation, Throwable $exception): void
    {
        $instance = WorkflowInstance::query()->find($instanceId);

        if ($instance === null) {
            return;
        }

        $actor = SystemActor::named(SystemActor::Automation);
        $this->runner->run('automation-failed', $instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $command, Effects $effects) use ($actor, $name, $origin, $correlation, $exception): TransitionResult {
            $this->history->record($instance, EventType::AutomationFailed, $actor, $correlation, $effects, metadata: ['automation' => $name, 'origin' => $origin, 'error' => class_basename($exception)]);

            return new TransitionResult($instance->ulid, 'automation', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
        });
    }

    private function finish(WorkflowInstance $instance, string $key, string $name, string $origin, string $outcome, ?string $next, string $correlation): void
    {
        $actor = SystemActor::named(SystemActor::Automation);

        try {
            $this->runner->run('automation', $instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $command, Effects $effects) use ($actor, $name, $origin, $outcome, $next, $correlation): TransitionResult {
                $this->history->record($instance, EventType::AutomationCompleted, $actor, $correlation, $effects, metadata: ['automation' => $name, 'origin' => $origin, 'outcome' => $outcome, 'next' => $next]);

                if ($next !== null && $outcome === 'completed') {
                    return $this->executor->apply($instance, $workflow, $next, $actor, null, [], $correlation, $effects);
                }

                return new TransitionResult($instance->ulid, 'automation', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
            }, idempotencyKey: $key);
        } catch (WorkflowException $exception) {
            // The handler's follow-up transition was refused: record that the automation ran, and why it stopped.
            report($exception);
            $this->runner->run('automation', $instance, $actor, function (WorkflowInstance $instance, Workflow $workflow, string $command, Effects $effects) use ($actor, $name, $origin, $next, $correlation, $exception): TransitionResult {
                $this->history->record($instance, EventType::AutomationFailed, $actor, $correlation, $effects, metadata: ['automation' => $name, 'origin' => $origin, 'next' => $next, 'error' => $exception instanceof InvalidTransitionException ? $exception->reason : class_basename($exception)]);

                return new TransitionResult($instance->ulid, 'automation', $instance->state, $instance->state, $instance->status->value, $instance->lock_version + 1, $correlation);
            }, idempotencyKey: $key);
        }
    }

    private function find(Workflow $workflow, string $name, string $origin): ?Automation
    {
        [$type, $target] = array_pad(explode(':', $origin, 2), 2, '');
        $candidates = $type === 'state'
            ? ($workflow->state($target)?->automations() ?? [])
            : array_merge(...array_map(static fn ($transition): array => $transition->nodeName() === $target ? $transition->automations() : [], $workflow->transitions()));

        foreach ($candidates as $automation) {
            if ($automation->nodeName() === $name) {
                return $automation;
            }
        }

        return null;
    }

    private function key(int $instance, string $name, string $origin, int $sequence): string
    {
        return 'automation|'.$instance.'|'.$origin.'|'.$sequence.'|'.$name;
    }
}
