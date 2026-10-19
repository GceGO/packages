<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

use Closure;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Events\WorkflowCommandRefused;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowConflictException;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\Models\WorkflowCommand;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Workflow;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs every mutating workflow command the same way:
 *
 * 1. replay: a command with a known idempotency key returns its first result;
 * 2. a transaction locks the instance row (SELECT ... FOR UPDATE);
 * 3. tenant scope: people only reach instances of the current tenant;
 * 4. optimistic check: a caller's expected version must still be current;
 * 5. the work runs; the instance is saved with a compare-and-swap on
 *    lock_version (a concurrent writer makes it fail: 409, rolled back);
 * 6. after the commit (never before; never after a rollback) the
 *    collected events, jobs and notifications are released.
 */
final readonly class CommandRunner
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private WorkflowDefinitions $definitions,
        private TenantContext $tenants,
        private Container $container,
        private WorkflowsConfig $config,
    ) {}

    /**
     * @param  Closure(WorkflowInstance, Workflow, string, Effects): TransitionResult  $work
     */
    public function run(string $command, WorkflowInstance $instance, Model|SystemActor $actor, Closure $work, ?int $expectedVersion = null, ?string $idempotencyKey = null): TransitionResult
    {
        $key = $idempotencyKey === null ? null : $this->key($command, $instance->ulid, $actor, $idempotencyKey);

        try {
            if ($key !== null && ($replay = $this->replay($key)) !== null) {
                return $replay;
            }

            $this->scope($instance, $actor);
            $effects = new Effects;

            $result = $this->connection()->transaction(function () use ($command, $instance, $actor, $work, $expectedVersion, $key, $effects): TransitionResult {
                $locked = WorkflowInstance::query()->whereKey($instance->id)->lockForUpdate()->first() ?? throw WorkflowNotFoundException::make('workflow instance');

                if ($expectedVersion !== null && $this->config->optimisticLocking && $locked->lock_version !== $expectedVersion) {
                    throw WorkflowConflictException::stale();
                }

                $result = $work($locked, $this->definitions->forInstance($locked), (string) Str::ulid(), $effects);
                $this->save($locked);

                if ($key !== null) {
                    $this->remember($key, $locked->id, $result);
                }

                $instance->setRawAttributes($locked->getAttributes(), true);
                $this->log($command, $locked, $actor, $result);

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent duplicate of the same idempotent command won, or a second running instance.
            $replay = $key === null ? null : $this->replay($key);

            return $replay ?? $this->refuse($command, $instance, $actor, WorkflowConflictException::stale());
        } catch (WorkflowException $exception) {
            $this->refuse($command, $instance, $actor, $exception);
        }

        $this->release($effects);

        return $result;
    }

    /**
     * Inserts a new instance and runs $work on it (start).
     *
     * @param  Closure(WorkflowInstance, Effects): void  $work
     */
    public function create(WorkflowInstance $instance, Model|SystemActor $actor, Closure $work, ?string $idempotencyKey = null): WorkflowInstance
    {
        $key = $idempotencyKey === null ? null : $this->key('start', $instance->workflow.'|'.$instance->subject_type.'#'.$instance->subject_id, $actor, $idempotencyKey);

        try {
            if ($key !== null && ($replay = $this->replay($key)) !== null) {
                return WorkflowInstance::query()->where('ulid', $replay->instance)->firstOrFail();
            }

            $effects = new Effects;

            $this->connection()->transaction(function () use ($instance, $work, $key, $effects, $actor): void {
                $instance->save();
                $work($instance, $effects);
                $this->save($instance);

                if ($key !== null) {
                    $this->remember($key, $instance->id, new TransitionResult($instance->ulid, 'start', '', $instance->state, $instance->status->value, $instance->lock_version, ''));
                }

                $this->log('start', $instance, $actor, null);
            });
        } catch (UniqueConstraintViolationException) {
            $replay = $key === null ? null : $this->replay($key);

            if ($replay !== null) {
                return WorkflowInstance::query()->where('ulid', $replay->instance)->firstOrFail();
            }

            $this->refuse('start', null, $actor, new WorkflowConflictException((string) __('gcego-workflows::messages.errors.running')));
        }

        $this->release($effects);

        return $instance;
    }

    public function hasRun(string $command, WorkflowInstance $instance, Model|SystemActor $actor, string $idempotencyKey): bool
    {
        return WorkflowCommand::query()->where('key', $this->key($command, $instance->ulid, $actor, $idempotencyKey))->exists();
    }

    /**
     * People only reach instances of the tenant they are working in.
     */
    public function scope(WorkflowInstance $instance, Model|SystemActor $actor): void
    {
        if ($actor instanceof Model && $this->config->enforceTenantScope && $instance->tenant !== $this->currentTenant()) {
            throw WorkflowNotFoundException::make('workflow instance');
        }
    }

    public function currentTenant(): ?string
    {
        $tenant = $this->tenants->tenant();

        return $tenant === null ? null : (string) $tenant->key;
    }

    public function refuse(string $command, ?WorkflowInstance $instance, Model|SystemActor $actor, WorkflowException $exception): never
    {
        $reason = $exception instanceof InvalidTransitionException ? $exception->reason : class_basename($exception);
        $this->events()->dispatch(new WorkflowCommandRefused($command, $instance?->ulid, $instance?->workflow, $reason, $actor instanceof SystemActor ? 'system' : 'user'));

        throw $exception;
    }

    /**
     * Compare-and-swap save: only when nobody changed the row since it was read.
     */
    private function save(WorkflowInstance $instance): void
    {
        $changes = array_intersect_key($instance->getAttributes(), $instance->getDirty());
        $version = Keys::int($instance->getOriginal('lock_version'));

        $updated = WorkflowInstance::query()->whereKey($instance->id)->where('lock_version', $version)
            ->update([...$changes, 'lock_version' => $version + 1, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw WorkflowConflictException::stale();
        }

        $instance->forceFill(['lock_version' => $version + 1])->syncOriginal();
    }

    private function release(Effects $effects): void
    {
        $this->connection()->afterCommit(function () use ($effects): void {
            foreach ($effects->events() as $event) {
                $this->events()->dispatch($event);
            }

            foreach ($effects->callbacks() as $callback) {
                $callback();
            }
        });
    }

    /**
     * The current dispatcher (resolved when used, so a swapped or decorated dispatcher is honoured).
     */
    private function events(): Dispatcher
    {
        return $this->container->make(Dispatcher::class);
    }

    private function replay(string $key): ?TransitionResult
    {
        $record = WorkflowCommand::query()->where('key', $key)->first();

        return $record === null ? null : TransitionResult::fromArray($record->result, true);
    }

    private function remember(string $key, int $instance, TransitionResult $result): void
    {
        $record = new WorkflowCommand;
        $record->forceFill(['key' => $key, 'instance_id' => $instance, 'result' => $result->toArray()])->save();
    }

    private function key(string $command, string $target, Model|SystemActor $actor, string $idempotencyKey): string
    {
        $who = $actor instanceof SystemActor ? 'system:'.$actor->name : $actor->getMorphClass().'#'.Keys::string($actor->getKey());

        return hash('sha256', implode('|', [$command, $target, $who, $idempotencyKey]));
    }

    private function log(string $command, WorkflowInstance $instance, Model|SystemActor $actor, ?TransitionResult $result): void
    {
        if ($this->config->logChannel === null) {
            return;
        }

        $this->connection()->afterCommit(fn () => Log::channel($this->config->logChannel)->info('gcego-workflows.'.$command, [
            'instance' => $instance->ulid,
            'workflow' => $instance->workflow,
            'version' => $instance->version,
            'tenant' => $instance->tenant,
            'actor' => $actor instanceof SystemActor ? 'system:'.$actor->name : 'user',
            'from' => $result?->from,
            'to' => $result->to ?? $instance->state,
            'correlation' => $result?->correlation,
        ]));
    }

    private function connection(): ConnectionInterface&Connection
    {
        $connection = $this->connections->connection($this->config->connection);

        return $connection instanceof Connection ? $connection : throw new \LogicException('Unsupported connection.');
    }
}
