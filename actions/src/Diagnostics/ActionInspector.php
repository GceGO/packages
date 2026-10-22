<?php

declare(strict_types=1);

namespace Gcego\Actions\Diagnostics;

use Gcego\Actions\ActionsConfig;
use Gcego\Actions\Input\InputResolvers;
use Gcego\Actions\Input\RulesInputResolver;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Security\Contracts\Requirement;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

/**
 * Describes a blueprint's actions without executing, authorizing or querying anything:
 * identity, type, handler, ability, confirmation, transaction, placement,
 * record requirement, input provider and renderer, plus definition errors
 * (duplicates, actions without authorization, Create/Edit without input,
 * restore/force-delete on models without SoftDeletes, record actions without a source).
 */
final readonly class ActionInspector
{
    public function __construct(
        private Container $container,
        private ActionResolver $resolver,
        private ActionsConfig $config,
        private InputResolvers $inputs,
    ) {}

    public function inspect(string $definition): ActionReport
    {
        try {
            $actions = $this->resolver->actions($definition);
        } catch (Throwable $exception) {
            return new ActionReport([], [new ActionDiagnostic('definition', ActionDiagnostic::Error, $exception->getMessage())]);
        }

        $summaries = [];
        $diagnostics = [];
        $renderers = $this->container->bound(RendererRegistry::class) ? $this->container->make(RendererRegistry::class) : null;

        foreach ($actions as $action) {
            $handler = $action->action->handler();
            $input = $this->inputs->for($action, $this->container->make(RulesInputResolver::class));
            $summaries[] = [
                'identity' => $action->identity(),
                'path' => $action->path,
                'type' => $action->action::class,
                'handler' => $handler?->describe(),
                'ability' => $action->action->ability(),
                'confirmation' => $action->requiresConfirmation($this->config),
                'transaction' => $action->usesTransaction($this->config),
                'placement' => $action->action->placements(),
                'record' => $action->action->requiresRecord(),
                'input' => $input === null ? null : $input::class,
                'renderer' => $renderers?->has($action->action) ?? false,
            ];

            array_push($diagnostics, ...$this->check($action, $handler !== null, $input !== null));
        }

        return new ActionReport($summaries, $diagnostics);
    }

    /**
     * @return list<ActionDiagnostic>
     */
    private function check(ResolvedAction $action, bool $hasHandler, bool $hasInput): array
    {
        $identity = $action->identity();
        $details = ['action' => $identity];
        $found = [];
        $own = array_filter($action->action->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement);

        if (! $hasHandler) {
            $found[] = new ActionDiagnostic('handler', ActionDiagnostic::Error, sprintf('%s has no Handler.', $identity), $details);
        }

        if ($action->action->ability() === null && $own === []) {
            $found[] = new ActionDiagnostic('authorization', ActionDiagnostic::Error, sprintf('%s declares no Ability and no requirement: it is denied to everyone.', $identity), $details);
        }

        if ($action->action::requiresInput() && ! $hasInput) {
            $found[] = new ActionDiagnostic('input', ActionDiagnostic::Error, sprintf('%s persists input but no InputResolver supports it (add an Input node or a form): it refuses to save.', $identity), $details);
        }

        if (($action->action->requiresRecord() === true || $action->action::requiresInput()) && $action->model() === null) {
            $found[] = new ActionDiagnostic('source', ActionDiagnostic::Error, sprintf('%s needs a blueprint Source with an Eloquent model.', $identity), $details);
        }

        if ($action->action::trashed() !== null && ($action->model() === null || ! in_array(SoftDeletes::class, class_uses_recursive($action->model()), true))) {
            $found[] = new ActionDiagnostic('soft-deletes', ActionDiagnostic::Error, sprintf('%s needs a model that uses SoftDeletes.', $identity), $details);
        }

        return $found === [] ? [new ActionDiagnostic('action', ActionDiagnostic::Ok, sprintf('%s is executable once authorized.', $identity), $details)] : $found;
    }
}
