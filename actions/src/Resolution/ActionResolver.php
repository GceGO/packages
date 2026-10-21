<?php

declare(strict_types=1);

namespace Gcego\Actions\Resolution;

use Gcego\Actions\Action;
use Gcego\Actions\Exceptions\ActionNotFoundException;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\Source;
use Gcego\Registry\Contracts\RegistryInterface;
use Throwable;

/**
 * Finds actions only inside trusted, registered blueprints.
 *
 * A request names a definition identity (chosen by server code) and an
 * action name; the name is looked up among that blueprint's own actions,
 * so knowing another module's action name grants nothing. Unknown names are
 * 404; duplicate names in one blueprint are a definition error.
 */
final readonly class ActionResolver
{
    public function __construct(private RegistryInterface $registry) {}

    /**
     * @throws ActionNotFoundException
     */
    public function resolve(string $definition, string $action): ResolvedAction
    {
        if (preg_match(Action::NamePattern, $action) !== 1) {
            throw ActionNotFoundException::in($definition);
        }

        foreach ($this->actions($definition) as $resolved) {
            if ($resolved->name() === $action) {
                return $resolved;
            }
        }

        throw ActionNotFoundException::in($definition);
    }

    /**
     * Every action of a registered blueprint, optionally for one placement, ordered.
     *
     * @return list<ResolvedAction>
     *
     * @throws ActionNotFoundException|InvalidActionException
     */
    public function actions(string $definition, ?string $placement = null): array
    {
        [$key, $blueprint] = $this->blueprint($definition);

        return self::collect($key, $blueprint, $placement);
    }

    /**
     * @return list<ResolvedAction>
     *
     * @throws InvalidActionException
     */
    public static function collect(string $key, BlueprintInterface $blueprint, ?string $placement = null): array
    {
        $source = $blueprint->part(BlueprintRole::Source);
        $actions = [];
        $names = [];

        foreach (app(ComponentWalker::class)->preOrder($blueprint) as $node) {
            $action = $node->component;

            if (! $action instanceof Action) {
                continue;
            }

            $name = $action->actionName();

            if (isset($names[$name])) {
                throw InvalidActionException::invalid(sprintf('"%s" in %s', $name, $key), 'two actions share this name; give one an explicit Name property.');
            }

            $names[$name] = true;
            $actions[] = new ResolvedAction($key, $blueprint, $action, $node->path->toString(), $source instanceof Source ? $source : null);
        }

        if ($placement !== null) {
            $actions = array_values(array_filter($actions, static fn (ResolvedAction $resolved): bool => in_array($placement, $resolved->action->placements(), true)));
        }

        $order = array_flip(array_map(static fn (ResolvedAction $resolved): string => $resolved->name(), $actions));
        usort($actions, static fn (ResolvedAction $a, ResolvedAction $b): int => [$a->action->order(), $order[$a->name()]] <=> [$b->action->order(), $order[$b->name()]]);

        return $actions;
    }

    /**
     * @return array{0: string, 1: BlueprintInterface}
     *
     * @throws ActionNotFoundException
     */
    public function blueprint(string $definition): array
    {
        try {
            $key = $this->registry->descriptor($definition)->key();
            $blueprint = $this->registry->get($key);
        } catch (Throwable) {
            throw ActionNotFoundException::in('this definition');
        }

        return $blueprint instanceof BlueprintInterface ? [$key, $blueprint] : throw ActionNotFoundException::in($key);
    }
}
