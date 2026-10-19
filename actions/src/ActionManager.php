<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Contracts\InputResolver;
use Gcego\Actions\Contracts\RendersInput;
use Gcego\Actions\Diagnostics\ActionInspector;
use Gcego\Actions\Diagnostics\ActionReport;
use Gcego\Actions\Exceptions\ActionExecutionException;
use Gcego\Actions\Execution\ActionExecutionEngine;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Input\InputResolvers;
use Gcego\Actions\Input\RulesInputResolver;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Actions\Resolution\ResolvedAction;
use Illuminate\Contracts\Container\Container;

/**
 * The entry point of gcego/actions (no facade).
 *
 *     $actions = app(ActionManager::class);
 *
 *     $actions->execute(new ActionRequest('blueprint:subjects', 'delete', record: 7));        // ConfirmationRequired + token
 *     $actions->execute(new ActionRequest('blueprint:subjects', 'delete', record: 7, confirmation: $token));
 *     $actions->execute(new ActionRequest('blueprint:subjects', 'create', input: $request->all()));
 *     $actions->available('blueprint:subjects', ActionPlacement::Row, record: 7);              // for rendering
 *
 * It resolves request-scoped services per call, so it is safe in long-running workers.
 */
final readonly class ActionManager
{
    public function __construct(private Container $container) {}

    public function execute(ActionRequest $request): ActionResult
    {
        return $this->engine()->execute($request);
    }

    /**
     * @throws ActionExecutionException
     */
    public function executeOrFail(ActionRequest $request): ActionResult
    {
        $result = $this->execute($request);

        return $result->successful() ? $result : throw ActionExecutionException::fromResult($result);
    }

    public function resolve(string $definition, string $action): ResolvedAction
    {
        return $this->container->make(ActionResolver::class)->resolve($definition, $action);
    }

    /**
     * @return list<ResolvedAction>
     */
    public function actions(string $definition, ?string $placement = null): array
    {
        return $this->container->make(ActionResolver::class)->actions($definition, $placement);
    }

    /**
     * Actions to show the current user: not hidden and currently authorized
     * (for the record, when given). Display only: execution checks again.
     *
     * @param  list<string>  $names  Restrict to these action names.
     * @return list<ResolvedAction>
     */
    public function available(string $definition, ?string $placement = null, string|int|null $record = null, array $names = []): array
    {
        $engine = $this->engine();

        return array_values(array_filter(
            $this->actions($definition, $placement),
            static fn (ResolvedAction $action): bool => ($names === [] || in_array($action->name(), $names, true)) && $engine->allows($action, $action->isBulk() ? null : $record),
        ));
    }

    public function allows(ResolvedAction $action, string|int|null $record = null): bool
    {
        return $this->engine()->allows($action, $record);
    }

    /**
     * Display-time check for a record already loaded through the blueprint's
     * scoped source (one table row), without another lookup. Execution
     * always re-resolves and re-authorizes by key.
     */
    public function allowsLoaded(ResolvedAction $action, mixed $record): bool
    {
        return $this->engine()->allowsLoaded($action, $record);
    }

    /**
     * @return array<string, mixed>
     */
    public function initialInput(ResolvedAction $action, string|int|null $record = null): array
    {
        return $this->engine()->initialInput($action, $record);
    }

    public function takesInput(ResolvedAction $action): bool
    {
        return $this->engine()->takesInput($action);
    }

    /**
     * HTML for an action's input dialog, when its InputResolver renders its own (gcego/forms).
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, list<string>>  $errors
     */
    public function renderInput(ResolvedAction $action, string|int|null $record, array $state, array $errors, string $prefix = 'input'): ?string
    {
        $resolver = $this->container->make(InputResolvers::class)->for($action, $this->container->make(RulesInputResolver::class));

        return $resolver instanceof RendersInput ? $resolver->renderInput($action, $record, $state, $errors, $prefix) : null;
    }

    public function inputResolver(InputResolver $resolver): void
    {
        $this->container->make(InputResolvers::class)->register($resolver);
    }

    public function placements(): ActionPlacement
    {
        return $this->container->make(ActionPlacement::class);
    }

    public function inspect(string $definition): ActionReport
    {
        return $this->container->make(ActionInspector::class)->inspect($definition);
    }

    private function engine(): ActionExecutionEngine
    {
        return $this->container->make(ActionExecutionEngine::class);
    }
}
