<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Closure;
use Gcego\Actions\Action;
use Gcego\Actions\ActionIntent;
use Gcego\Actions\ActionsConfig;
use Gcego\Actions\Bulk\BulkPolicy;
use Gcego\Actions\Bulk\SelectionResolver;
use Gcego\Actions\BulkAction;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Lifecycle;
use Gcego\Actions\Confirmation\ConfirmationManager;
use Gcego\Actions\Contracts\ActionAuthorizer;
use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\ActionConfirmationException;
use Gcego\Actions\Exceptions\ActionNotFoundException;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Input\InputResolvers;
use Gcego\Actions\Input\RulesInputResolver;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Data\Exceptions\InvalidQueryException;
use Gcego\Data\Exceptions\RecordNotFoundException;
use Gcego\Data\Query\QueryContext;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Records\RecordAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Executes one action request, in this order:
 *
 *  1. resolve the trusted action from its registered blueprint      (404 when unknown)
 *  2. prepare hook (read-only); hidden or disabled actions stop here
 *  3. the security context: user, guard, verified tenant
 *  4. the record (scoped lookup through gcego/data, trashed rows only for
 *     restore/force-delete) or, for bulk actions, the selection
 *  5. authorization (ActionAuthorizer): definition chain, own requirement, ability, tenant
 *  6. idempotency claim (Idempotent actions)
 *  7. confirmation: a token is issued (nothing runs) or verified and consumed
 *  8. validated, writable input (InputResolver); Create/Edit refuse without it
 *  9. transaction: beforeExecute hook, then the handler
 * 10. commit or roll back; after-commit effects and afterSuccess/afterFailure hooks
 * 11. a normalized, safe ActionResult and the ActionCompleted event
 *
 * Nothing mutates before step 9. Unexpected exceptions are reported to
 * Laravel's handler and answered with a generic failure message.
 */
final readonly class ActionExecutionEngine
{
    public function __construct(
        private Container $container,
        private ActionResolver $resolver,
        private ActionAuthorizer $authorizer,
        private ConfirmationManager $confirmations,
        private IdempotencyGuard $idempotency,
        private TransactionCoordinator $transactions,
        private InputResolvers $inputs,
        private SelectionResolver $selections,
        private ActionsConfig $config,
        private Dispatcher $events,
        private ExceptionHandler $exceptions,
    ) {}

    public function execute(ActionRequest $request): ActionResult
    {
        $identity = ResolvedAction::identityOf($request->definition, $request->action);
        $resolved = null;
        $context = null;
        $claimed = false;

        try {
            $resolved = $this->resolver->resolve($request->definition, $request->action);
            $identity = $resolved->identity();
            $this->hook($resolved, 'prepare', $resolved);

            if ($resolved->action->isHidden() || $resolved->action->isDisabled()) {
                return $this->finish($resolved, null, ActionResult::denied($identity, self::message('denied'), reason: 'unavailable'));
            }

            $security = $this->container->make(ContextFactory::class)->current();

            if ($resolved->action instanceof BulkAction) {
                return $this->finish($resolved, null, $this->bulk($resolved, $request, $security));
            }

            [$record, $denial] = $this->record($resolved, $request, $security);

            if ($denial !== null) {
                return $this->finish($resolved, null, $denial);
            }

            $decision = $this->authorizer->authorize($resolved, $security, $record);

            if ($decision->denied()) {
                return $this->finish($resolved, null, self::deniedResult($identity, $decision));
            }

            if ($resolved->action->get(Action::Idempotent) === true) {
                if ($request->idempotencyKey === null) {
                    return $this->finish($resolved, null, ActionResult::conflict($identity, self::message('idempotency_required'), 'idempotency_required'));
                }

                if (! $this->idempotency->claim($identity, $security, $request->idempotencyKey)) {
                    return $this->finish($resolved, null, ActionResult::conflict($identity, self::message('duplicate'), 'duplicate'));
                }

                $claimed = true;
            }

            $recordKey = $record instanceof Model ? self::key($record) : $request->record;

            if ($resolved->requiresConfirmation($this->config)) {
                if ($request->confirmation === null) {
                    $this->releaseClaim($claimed, $resolved, $security, $request);

                    return ActionResult::confirmationRequired($identity, self::message('confirmation_required'), $this->confirmationFor($resolved, $security, $recordKey));
                }

                $this->confirmations->consume($request->confirmation, $resolved, $security, $recordKey);
            }

            $context = new ActionContext($resolved, $security, $record);
            $context = $context->withInput($this->input($resolved, $context, $request->input));
            $result = $this->run($resolved, $context, $record);

            if (! $result->successful()) {
                $this->releaseClaim($claimed, $resolved, $security, $request);
            }

            return $this->finish($resolved, $context, $result);
        } catch (Throwable $exception) {
            if ($claimed && $resolved !== null && $request->idempotencyKey !== null) {
                $this->idempotency->release($resolved->identity(), $this->container->make(ContextFactory::class)->current(), $request->idempotencyKey);
            }

            $result = $this->failure($identity, $exception, $resolved);

            return $resolved === null ? $result : $this->finish($resolved, $context, $result, $exception);
        }
    }

    /**
     * Whether the action is available to the current user (for rendering): never a substitute for execute().
     */
    public function allows(ResolvedAction $resolved, string|int|null $record = null): bool
    {
        if ($resolved->action->isHidden()) {
            return false;
        }

        try {
            $security = $this->container->make(ContextFactory::class)->current();
            [$resolvedRecord, $denial] = $this->record($resolved, new ActionRequest($resolved->definition, $resolved->name(), $record), $security);

            return $denial === null && $this->authorizer->authorize($resolved, $security, $resolvedRecord)->allowed;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether to offer the action for a record the caller already loaded
     * through the blueprint's scoped source (a table row). For display only:
     * execution re-resolves and re-authorizes the record by key. Trash-only
     * actions are offered for trashed records only, and others never for trashed ones.
     * Bulk actions are checked with their per-record ability on that record.
     */
    public function allowsLoaded(ResolvedAction $resolved, mixed $record): bool
    {
        if ($resolved->action->isHidden() || (! $resolved->isBulk() && $resolved->action->requiresRecord() === false)) {
            return false;
        }

        $trashed = $record instanceof Model && in_array(SoftDeletes::class, class_uses_recursive($record), true) && method_exists($record, 'trashed') && $record->trashed() === true;

        if (($resolved->action::trashed() === 'only') !== $trashed) {
            return false;
        }

        try {
            return $this->authorizer->authorize($resolved, $this->container->make(ContextFactory::class)->current(), $record)->allowed;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Initial input values for a dialog: the record's readable, editable fields.
     *
     * @return array<string, mixed>
     */
    public function initialInput(ResolvedAction $resolved, string|int|null $record = null): array
    {
        $security = $this->container->make(ContextFactory::class)->current();
        [$resolvedRecord, $denial] = $this->record($resolved, new ActionRequest($resolved->definition, $resolved->name(), $record), $security);
        $resolver = $this->inputs->for($resolved, $this->container->make(RulesInputResolver::class));

        return $denial !== null || $resolver === null ? [] : $resolver->initial($resolved, new ActionContext($resolved, $security, $resolvedRecord));
    }

    /**
     * Whether the action collects input (a dialog with fields).
     */
    public function takesInput(ResolvedAction $resolved): bool
    {
        return $resolved->action::requiresInput() || $this->inputs->for($resolved, $this->container->make(RulesInputResolver::class)) !== null;
    }

    /**
     * @return array{0: mixed, 1: ActionResult|null}
     */
    private function record(ResolvedAction $resolved, ActionRequest $request, AuthorizationContext $security): array
    {
        $required = $resolved->action->requiresRecord();
        $identity = $resolved->identity();

        if ($request->record === null) {
            return $required === true ? [null, ActionResult::failed($identity, self::message('record_required'), 'record_required')] : [null, null];
        }

        if ($required === false) {
            return [null, ActionResult::failed($identity, self::message('record_not_accepted'), 'record_not_accepted')];
        }

        $source = $resolved->recordSource();

        if ($source === null) {
            throw InvalidActionException::invalid($identity, 'record actions need a Source part in the blueprint.');
        }

        if ($resolved->action::trashed() !== null && ! in_array(SoftDeletes::class, class_uses_recursive((string) $resolved->model()), true)) {
            throw InvalidActionException::invalid($identity, 'restore and force-delete need a model that uses SoftDeletes.');
        }

        [$decision, $record] = $this->container->make(RecordAuthorizer::class)->inspect($source, $request->record, $resolved->action->ability() ?? 'view', DenialStrategy::Forbidden);

        return $decision->denied() ? [null, self::deniedResult($identity, $decision)] : [$record, null];
    }

    /**
     * @param  array<array-key, mixed>  $submitted
     */
    private function input(ResolvedAction $resolved, ActionContext $context, array $submitted): ValidatedInput
    {
        $resolver = $this->inputs->for($resolved, $this->container->make(RulesInputResolver::class));

        if ($resolver === null) {
            if ($resolved->action::requiresInput()) {
                throw InvalidActionException::invalid($resolved->identity(), 'it persists input but no InputResolver supports it; add an Input node or a gcego/forms schema. Nothing was saved.');
            }

            return ValidatedInput::none();
        }

        return $resolver->resolve($resolved, $context, $submitted);
    }

    private function run(ResolvedAction $resolved, ActionContext $context, mixed $record): ActionResult
    {
        $handler = $resolved->action->handler() ?? throw InvalidActionException::invalid($resolved->identity(), 'it has no Handler.');
        $connection = $resolved->transactionConnection();
        $lifecycle = $this->lifecycle($resolved);

        $result = $this->transactions->run($connection, $resolved->usesTransaction($this->config), function () use ($resolved, $context, $handler, $connection, $lifecycle): ActionResult {
            if ($lifecycle?->beforeExecute !== null) {
                ($lifecycle->beforeExecute)($context);
            }

            $returned = $this->invoke($handler, $context);

            if ($returned instanceof Model) {
                foreach ($context->input->afterSave as $callback) {
                    $callback($returned);
                }
            }

            $result = $this->normalize($resolved, $context, $returned);

            if ($result->successful()) {
                foreach ($context->effects->pendingAfterCommit() as $callback) {
                    $this->transactions->afterCommit($connection, $callback);
                }

                if ($lifecycle?->afterSuccess !== null) {
                    $hook = $lifecycle->afterSuccess;
                    $this->transactions->afterCommit($connection, fn () => $this->safely(static fn () => $hook($context, $result)));
                }
            }

            return $result;
        }, $resolved->transactionAttempts());

        return $result;
    }

    private function invoke(Handler $handler, ActionContext $context): mixed
    {
        if ($handler->handler instanceof Closure) {
            return $this->container->call($handler->handler, ['context' => $context]);
        }

        $instance = $this->container->make($handler->handler);

        return $instance instanceof ActionHandler ? $instance->handle($context) : throw InvalidActionException::invalid($context->identity(), 'its handler is not an ActionHandler.');
    }

    private function normalize(ResolvedAction $resolved, ActionContext $context, mixed $outcome): ActionResult
    {
        if ($outcome instanceof ActionResult) {
            return $outcome;
        }

        if ($outcome === false) {
            return ActionResult::failed($resolved->identity(), self::failureMessage($resolved), 'handler_returned_false');
        }

        $record = $outcome instanceof Model ? $outcome : $context->record;

        return ActionResult::success($resolved->identity(), self::successMessage($resolved), $record, $context->effects);
    }

    private function bulk(ResolvedAction $resolved, ActionRequest $request, AuthorizationContext $security): ActionResult
    {
        $identity = $resolved->identity();
        /** @var BulkAction $action */
        $action = $resolved->action;

        try {
            $keys = $this->selections->validate($request->selection);
        } catch (InvalidArgumentException $exception) {
            return ActionResult::failed($identity, $exception->getMessage(), 'invalid_selection');
        }

        $decision = $this->authorizer->authorize($resolved, $security);

        if ($decision->denied()) {
            return self::deniedResult($identity, $decision);
        }

        $queryContext = QueryContext::for($security->user);

        // Pass 1: every selected key must exist in scope and be authorized (or, partially, is skipped).
        $allowed = [];
        $unavailable = 0;

        foreach ($this->selections->chunks($keys) as $chunk) {
            $records = $this->selections->resolve($resolved, $chunk, $queryContext);

            foreach ($chunk as $key) {
                $record = $records[(string) $key] ?? null;

                if ($record !== null && $this->authorizer->authorize($resolved, $security, $record)->allowed) {
                    $allowed[] = $key;
                } else {
                    $unavailable++;
                }
            }
        }

        if ($allowed === [] || ($unavailable > 0 && $action->policy() === BulkPolicy::AllOrNothing)) {
            return ActionResult::denied($identity, self::message('bulk_unavailable', ['count' => $unavailable]), reason: 'selection_unavailable');
        }

        if ($resolved->requiresConfirmation($this->config)) {
            if ($request->confirmation === null) {
                return ActionResult::confirmationRequired($identity, self::message('confirmation_required'), $this->confirmationFor($resolved, $security, null, $allowed));
            }

            $this->confirmations->consume($request->confirmation, $resolved, $security, null, $allowed);
        }

        $base = new ActionContext($resolved, $security, selection: $allowed);
        $base = $base->withInput($this->input($resolved, $base, $request->input));
        $handler = $action->handler() ?? throw InvalidActionException::invalid($identity, 'it has no Handler.');
        $connection = $resolved->transactionConnection();
        $transactional = $resolved->usesTransaction($this->config);
        $processed = 0;
        $failed = 0;

        // Pass 2: re-resolve and re-authorize each record inside the transaction, then run the handler.
        $process = function (array $chunk) use ($resolved, $security, $queryContext, $base, $handler, $connection, &$processed, &$failed, $action, $transactional): void {
            $chunk = array_values(array_filter($chunk, static fn (mixed $key): bool => is_int($key) || is_string($key)));
            $records = $this->selections->resolve($resolved, $chunk, $queryContext);

            foreach ($chunk as $key) {
                $record = $records[(string) $key] ?? null;

                if ($record === null || $this->authorizer->authorize($resolved, $security, $record)->denied()) {
                    if ($action->policy() === BulkPolicy::AllOrNothing) {
                        throw new AuthorizationException('A selected record is no longer available.');
                    }

                    $failed++;

                    continue;
                }

                $context = $base->withRecord($record);

                if ($action->policy() === BulkPolicy::AllOrNothing) {
                    $this->bulkStep($handler, $context, $resolved, $connection);
                    $processed++;

                    continue;
                }

                try {
                    $this->transactions->run($connection, $transactional, fn () => $this->bulkStep($handler, $context, $resolved, $connection));
                    $processed++;
                } catch (Throwable $exception) {
                    $this->exceptions->report($exception);
                    $failed++;
                }
            }
        };

        if ($action->policy() === BulkPolicy::AllOrNothing) {
            $this->transactions->run($connection, $transactional, function () use ($allowed, $process): void {
                foreach ($this->selections->chunks($allowed) as $chunk) {
                    $process($chunk);
                }
            });
        } else {
            foreach ($this->selections->chunks($allowed) as $chunk) {
                $process($chunk);
            }
        }

        $data = ['selected' => count($keys), 'processed' => $processed, 'skipped' => $unavailable, 'failed' => $failed];

        return $processed === 0
            ? ActionResult::failed($identity, self::failureMessage($resolved), 'bulk_failed', $data)
            : ActionResult::success($identity, self::message('bulk_done', ['processed' => $processed, 'total' => count($keys)], $action->get(Action::SuccessMessage)), null, $base->effects, $data);
    }

    private function bulkStep(Handler $handler, ActionContext $context, ResolvedAction $resolved, ?string $connection): void
    {
        $lifecycle = $this->lifecycle($resolved);

        if ($lifecycle?->beforeExecute !== null) {
            ($lifecycle->beforeExecute)($context);
        }

        $result = $this->normalize($resolved, $context, $this->invoke($handler, $context));

        if (! $result->successful()) {
            throw new \RuntimeException('The bulk handler failed for one record.');
        }

        foreach ($context->effects->pendingAfterCommit() as $callback) {
            $this->transactions->afterCommit($connection, $callback);
        }
    }

    /**
     * @param  list<string|int>  $selection
     * @return array{token: string, title: string, message: string, confirm: string, cancel: string, destructive: bool}
     */
    private function confirmationFor(ResolvedAction $resolved, AuthorizationContext $security, string|int|null $record, array $selection = []): array
    {
        $node = $resolved->confirmation();
        $label = $resolved->action->label();

        return [
            'token' => $this->confirmations->issue($resolved, $security, $record, $selection),
            'title' => $node->title ?? (string) __('gcego-actions::messages.confirm_title', ['label' => __($label)]),
            'message' => $node->message ?? (string) __($resolved->action->intent() === ActionIntent::Destructive ? 'gcego-actions::messages.confirm_destructive' : 'gcego-actions::messages.confirm_message'),
            'confirm' => $node->confirmLabel ?? (string) __($label),
            'cancel' => $node->cancelLabel ?? (string) __('gcego-actions::messages.cancel'),
            'destructive' => $resolved->action->intent() === ActionIntent::Destructive,
        ];
    }

    private function failure(string $identity, Throwable $exception, ?ResolvedAction $resolved): ActionResult
    {
        return match (true) {
            $exception instanceof ActionNotFoundException => ActionResult::notFound($identity, self::message('not_found')),
            $exception instanceof RecordNotFoundException => ActionResult::notFound($identity, self::message('not_found')),
            $exception instanceof AuthorizationException => ActionResult::denied($identity, $exception->status() === 404 ? self::message('not_found') : self::message('denied'), $exception->status() ?? 403, 'denied'),
            $exception instanceof ValidationException => ActionResult::invalid($identity, self::message('validation_failed'), self::errors($exception)),
            $exception instanceof ActionConfirmationException => ActionResult::conflict($identity, self::message('confirmation_invalid'), 'confirmation_invalid'),
            $exception instanceof InvalidQueryException => ActionResult::failed($identity, self::message('invalid_request'), 'invalid_request'),
            default => $this->unexpected($identity, $exception, $resolved),
        };
    }

    private function unexpected(string $identity, Throwable $exception, ?ResolvedAction $resolved): ActionResult
    {
        $this->exceptions->report($exception);

        return ActionResult::failed($identity, $resolved === null ? self::message('failed') : self::failureMessage($resolved), $exception instanceof InvalidActionException ? 'invalid_action' : 'exception');
    }

    private function finish(ResolvedAction $resolved, ?ActionContext $context, ActionResult $result, ?Throwable $exception = null): ActionResult
    {
        $lifecycle = $this->lifecycle($resolved);

        if (! $result->successful() && $result->status !== ActionStatus::ConfirmationRequired && $lifecycle?->afterFailure !== null) {
            $hook = $lifecycle->afterFailure;
            $this->safely(static fn () => $hook($context, $result, $exception));
        }

        if ($lifecycle?->afterComplete !== null) {
            $hook = $lifecycle->afterComplete;
            $this->safely(static fn () => $hook($context, $result));
        }

        $event = new ActionCompleted($result, $context?->authorization->userKey());
        $result->successful() ? $this->transactions->afterCommit($resolved->transactionConnection(), fn () => $this->events->dispatch($event)) : $this->events->dispatch($event);

        return $result;
    }

    private function hook(ResolvedAction $resolved, string $name, mixed ...$arguments): void
    {
        $hook = $this->lifecycle($resolved)?->{$name};

        if ($hook instanceof Closure) {
            $hook(...$arguments);
        }
    }

    private function lifecycle(ResolvedAction $resolved): ?Lifecycle
    {
        $node = $resolved->action->configurations()->first(Lifecycle::class);

        return $node instanceof Lifecycle ? $node : null;
    }

    private function safely(Closure $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
        }
    }

    private function releaseClaim(bool $claimed, ResolvedAction $resolved, AuthorizationContext $security, ActionRequest $request): void
    {
        if ($claimed && $request->idempotencyKey !== null) {
            $this->idempotency->release($resolved->identity(), $security, $request->idempotencyKey);
        }
    }

    private static function deniedResult(string $identity, AuthorizationDecision $decision): ActionResult
    {
        return ActionResult::denied($identity, $decision->status === 404 ? self::message('not_found') : self::message('denied'), $decision->status, $decision->reason?->value);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function errors(ValidationException $exception): array
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            $errors[(string) $field] = array_values(array_filter(is_array($messages) ? $messages : [$messages], is_string(...)));
        }

        return $errors;
    }

    private static function successMessage(ResolvedAction $resolved): string
    {
        $message = $resolved->action->get(Action::SuccessMessage);

        return (string) __(is_string($message) ? $message : 'gcego-actions::messages.executed', ['label' => __($resolved->action->label())]);
    }

    private static function failureMessage(ResolvedAction $resolved): string
    {
        $message = $resolved->action->get(Action::FailureMessage);

        return (string) __(is_string($message) ? $message : 'gcego-actions::messages.failed', ['label' => __($resolved->action->label())]);
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    private static function message(string $key, array $replace = [], mixed $custom = null): string
    {
        return (string) __(is_string($custom) ? $custom : 'gcego-actions::messages.'.$key, $replace);
    }

    private static function key(Model $record): string|int|null
    {
        $key = $record->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }
}
