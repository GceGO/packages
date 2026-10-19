<?php

declare(strict_types=1);

namespace Gcego\Settings\Writing;

use Gcego\Settings\Approvals\SettingApprovals;
use Gcego\Settings\Cache\SettingsCache;
use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Contracts\SettingsRepository;
use Gcego\Settings\Contracts\SettingValidator;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Events\SecretRotated;
use Gcego\Settings\Events\SettingReset;
use Gcego\Settings\Events\SettingsImported;
use Gcego\Settings\Events\SettingsUpdated;
use Gcego\Settings\Events\SettingUpdated;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\Exceptions\SettingScopeException;
use Gcego\Settings\History\ChangeType;
use Gcego\Settings\History\SettingsHistoryRepository;
use Gcego\Settings\Integration\SettingsNotifier;
use Gcego\Settings\Persistence\StoredValue;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Security\SettingEncryption;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\Support\SettingsLogger;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Types\SettingSerializer;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every change to stored settings, in this order:
 *
 * 1. only registered keys, only at scopes they allow, only at a target the
 *    server resolved (SettingNotFoundException, SettingScopeException);
 * 2. authorization of the person for every key (SettingAuthorizationException),
 *    before anything is validated or read;
 * 3. casting and validation, dependencies included (InvalidSettingException);
 * 4. one transaction: rows locked, versions compared (SettingConflictException),
 *    values serialized as JSON and encrypted when sensitive, history recorded,
 *    approval requests opened. Any failure rolls back the whole write unless
 *    a partial write was asked for (then only invalid keys are left out);
 * 5. after commit only: cache invalidation for the scope instance, events,
 *    notifications and log entries (never with values).
 *
 * Writing the value already stored changes nothing (no version, no history).
 */
final readonly class SettingsWriter
{
    public function __construct(
        private SettingsDefinitions $definitions,
        private SettingsRepository $repository,
        private SettingsAuthorizer $authorizer,
        private SettingValidator $validator,
        private SettingsReader $reader,
        private SettingsCache $cache,
        private SettingSerializer $serializer,
        private SettingEncryption $encryption,
        private SettingsHistoryRepository $history,
        private SettingApprovals $approvals,
        private SettingsNotifier $notifier,
        private SettingsLogger $logger,
        private Dispatcher $events,
        private SettingCaster $caster = new SettingCaster,
    ) {}

    /**
     * @param  array<array-key, mixed>  $values  Key => value (untrusted values are fine: everything is checked).
     * @param  array<array-key, mixed>  $expected  Key => the version the caller saw at the target (0: none).
     */
    public function write(ScopeContext $context, array $values, SettingsActor $actor, array $expected = [], ChangeType $change = ChangeType::Updated, bool $partial = false): SettingsWriteResult
    {
        $definitions = $this->authorized($context, array_keys($values), $actor, 'change');
        $values = array_intersect_key($values, $definitions);
        $result = $this->validator->validate($this->stringKeys($values), $definitions, $context);
        $errors = $result->errors;

        if ($errors !== [] && ! $partial) {
            $this->logger->info('validation_failed', ['keys' => array_keys($errors), 'scope' => $context->target->value]);

            throw InvalidSettingException::values($errors);
        }

        $typed = $result->values;
        $correlation = (string) Str::uuid();
        $scopeKey = (string) $context->targetKey();
        $versions = $this->versions($expected);

        $outcome = $this->repository->transaction(function () use ($context, $typed, $definitions, $actor, $versions, $change, $correlation, $scopeKey): array {
            $keys = array_keys($typed);
            sort($keys);
            $current = $this->repository->lock($keys, $context->target, $scopeKey);
            $applied = $unchanged = $pending = $effects = [];

            foreach ($keys as $key) {
                $definition = $definitions[$key];
                $before = $current[$key] ?? null;
                $old = null;

                if ($before !== null) {
                    try {
                        $old = $this->reader->decode($definition, $before);
                    } catch (InvalidSettingException) {
                        $old = null;
                    }
                }

                if (isset($versions[$key]) && ($before->version ?? 0) !== $versions[$key]) {
                    throw SettingConflictException::for([$key]);
                }

                if ($before !== null && $this->sameStored($definition, $before, $old, $typed[$key])) {
                    $unchanged[] = $key;

                    continue;
                }

                if ($this->approvals->requires($definition, $actor)) {
                    $pending[$key] = $this->approvals->open($definition, $context, $typed[$key], $actor, $before->version ?? 0, $correlation)->ulid;

                    continue;
                }

                $payload = $this->serializer->serialize($definition, $typed[$key]);
                $stored = $this->repository->put($key, $context->target, $scopeKey, $definition->encrypted() ? $this->encryption->encrypt($payload) : $payload, $definition->type->value, $definition->encrypted(), $before->version ?? 0, $actor->reference());
                $type = $definition->sensitive && $before !== null && $change === ChangeType::Updated ? ChangeType::Rotated : $change;
                $this->history->record($definition, $context, $type, $actor, $old, $typed[$key], $before !== null, $stored->version, $correlation);
                $applied[$key] = $stored->version;
                $effects[] = [$definition, $type, $stored->version];
            }

            return [$applied, $unchanged, $pending, $effects];
        });

        [$applied, $unchanged, $pending, $effects] = $outcome;
        $this->afterCommit($context, $actor, $correlation, $effects, $change);

        return new SettingsWriteResult($applied, $unchanged, $pending, $errors, $correlation);
    }

    /**
     * Remove overrides at the target (the settings inherit again), after authorization and dependency checks.
     *
     * @param  list<string>  $keys
     * @param  array<array-key, mixed>  $expected
     */
    public function reset(ScopeContext $context, array $keys, SettingsActor $actor, array $expected = []): SettingsWriteResult
    {
        $definitions = $this->authorized($context, $keys, $actor, 'reset');
        $inherited = $this->reader->resolveMany(array_values($definitions), $context, skipTarget: true);
        $after = [];

        foreach ($inherited as $key => $resolved) {
            $after[$key] = $resolved->value;
        }

        $this->validator->validate($after, $definitions, $context)->throwIfFailed();
        $correlation = (string) Str::uuid();
        $scopeKey = (string) $context->targetKey();
        $versions = $this->versions($expected);

        [$applied, $unchanged, $resets] = $this->repository->transaction(function () use ($context, $definitions, $actor, $versions, $correlation, $scopeKey): array {
            $applied = $unchanged = $resets = [];
            $keys = array_keys($definitions);
            sort($keys);

            foreach ($keys as $key) {
                $definition = $definitions[$key];
                $removed = $this->repository->forget($key, $context->target, $scopeKey, $versions[$key] ?? null);

                if ($removed === null) {
                    $unchanged[] = $key;

                    continue;
                }

                try {
                    $old = $this->reader->decode($definition, $removed);
                } catch (InvalidSettingException) {
                    $old = null;
                }

                $this->history->record($definition, $context, ChangeType::Reset, $actor, $old, null, true, 0, $correlation);
                $applied[$key] = 0;
                $resets[] = $definition;
            }

            return [$applied, $unchanged, $resets];
        });

        $this->repository->afterCommit(function () use ($context, $actor, $correlation, $resets): void {
            if ($resets === []) {
                return;
            }

            $this->cache->invalidate($context->target, (string) $context->targetKey());

            foreach ($resets as $definition) {
                $this->dispatch(new SettingReset($definition->key, $definition->group, $context->target->value, $context->tenant?->id(), $definition->sensitive, $actor->reference(), $correlation));
            }

            $this->dispatchBatch($context, $actor, $correlation, $resets);
            $this->logger->info('reset', ['keys' => array_map(static fn (SettingDefinition $definition): string => $definition->key, $resets), 'scope' => $context->target->value, 'actor' => $actor->reference(), 'correlation' => $correlation]);
        });

        return new SettingsWriteResult($applied, $unchanged, [], [], $correlation);
    }

    /**
     * Replace a sensitive value. Neither value is kept in history or events.
     */
    public function rotate(ScopeContext $context, string $key, mixed $secret, SettingsActor $actor, ?int $expected = null): SettingsWriteResult
    {
        $definition = $this->definitions->definition($key);

        if (! $definition->sensitive) {
            throw InvalidSettingException::definition(sprintf('The setting "%s"', $key), 'is not sensitive; use set().');
        }

        if ($secret === null || $secret === '') {
            throw InvalidSettingException::values([$key => [(string) __('gcego-settings::messages.validation.secret', ['attribute' => $definition->label()])]]);
        }

        return $this->write($context, [$key => $secret], $actor, $expected === null ? [] : [$key => $expected], ChangeType::Rotated);
    }

    /**
     * @param  list<array-key>  $keys
     * @return array<string, SettingDefinition>
     */
    private function authorized(ScopeContext $context, array $keys, SettingsActor $actor, string $operation): array
    {
        if ($context->targetKey() === null) {
            throw SettingScopeException::because(sprintf('There is no %s to store settings for here.', $context->target->value));
        }

        $definitions = [];

        foreach ($keys as $key) {
            $key = is_string($key) ? $key : (string) $key;
            $definition = $this->definitions->definition($key);

            if (! $definition->allows($context->target)) {
                throw SettingScopeException::notAllowed($key, $context->target->value);
            }

            $definitions[$key] = $definition;
        }

        if ($actor->isSystem()) {
            return $definitions;
        }

        foreach ($definitions as $key => $definition) {
            if (! $this->authorizer->canUpdate($actor->user, $definition, $context)) {
                $this->logger->warning('authorization_denied', ['key' => $key, 'scope' => $context->target->value, 'operation' => $operation, 'actor' => $actor->reference()]);

                throw SettingAuthorizationException::for($operation, $key);
            }
        }

        return $definitions;
    }

    /**
     * @param  list<array{0: SettingDefinition, 1: ChangeType, 2: int}>  $effects
     */
    private function afterCommit(ScopeContext $context, SettingsActor $actor, string $correlation, array $effects, ChangeType $change): void
    {
        $this->repository->afterCommit(function () use ($context, $actor, $correlation, $effects, $change): void {
            if ($effects === []) {
                return;
            }

            $this->cache->invalidate($context->target, (string) $context->targetKey());
            $definitions = [];

            foreach ($effects as [$definition, $type, $version]) {
                $definitions[] = $definition;
                $this->dispatch(new SettingUpdated($definition->key, $definition->group, $context->target->value, $context->tenant?->id(), $type, $version, $definition->sensitive, $actor->reference(), $correlation));

                if ($definition->sensitive) {
                    $this->dispatch(new SecretRotated($definition->key, $definition->group, $context->target->value, $context->tenant?->id(), $version, $actor->reference(), $correlation));
                }
            }

            $this->dispatchBatch($context, $actor, $correlation, $definitions);

            if ($change === ChangeType::Imported) {
                $this->dispatch(new SettingsImported(array_map(static fn (SettingDefinition $definition): string => $definition->key, $definitions), $context->target->value, $context->tenant?->id(), $actor->reference(), $correlation));
            }

            $this->notifier->changed($definitions, $context, $correlation);
            $this->logger->info('updated', ['keys' => array_map(static fn (SettingDefinition $definition): string => $definition->key, $definitions), 'scope' => $context->target->value, 'tenant' => $context->tenant?->id(), 'actor' => $actor->reference(), 'change' => $change->value, 'correlation' => $correlation]);
        });
    }

    /**
     * @param  list<SettingDefinition>  $definitions
     */
    private function dispatchBatch(ScopeContext $context, SettingsActor $actor, string $correlation, array $definitions): void
    {
        $sensitive = array_filter($definitions, static fn (SettingDefinition $definition): bool => $definition->sensitive) !== [];

        $this->dispatch(new SettingsUpdated(
            array_map(static fn (SettingDefinition $definition): string => $definition->key, $definitions),
            array_values(array_unique(array_map(static fn (SettingDefinition $definition): string => $definition->group, $definitions))),
            $context->target->value,
            $context->tenant?->id(),
            $actor->reference(),
            $correlation,
            $sensitive,
        ));
    }

    /**
     * Listeners run after the commit; one failing never undoes the committed write.
     */
    private function dispatch(object $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function sameStored(SettingDefinition $definition, StoredValue $before, mixed $old, mixed $new): bool
    {
        return $before->encrypted === $definition->encrypted() && $before->type === $definition->type->value && $this->caster->same($definition->type, $old, $new) && ($old !== null || $new === null);
    }

    /**
     * @param  array<array-key, mixed>  $expected
     * @return array<string, int>
     */
    private function versions(array $expected): array
    {
        $versions = [];

        foreach ($expected as $key => $version) {
            if (is_int($version) && $version >= 0) {
                $versions[(string) $key] = $version;
            } elseif (is_string($version) && ctype_digit($version)) {
                $versions[(string) $key] = (int) $version;
            }
        }

        return $versions;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeys(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $clean[(string) $key] = $value;
        }

        return $clean;
    }
}
