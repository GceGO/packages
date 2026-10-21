<?php

declare(strict_types=1);

namespace Gcego\Components\Evaluation;

use Closure;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\EvaluationException;
use Gcego\Components\Facades\Components;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\QueueableEntity;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Calls closures with their parameters injected, Filament style.
 *
 * Each parameter is resolved, in order, by name from the context (and the
 * built-in $component, $get, $set and $state), by type from the context, by
 * type from the container, then by its default value or null. Records
 * (QueueableEntity, such as models) are only injected from the context: the
 * container would build an empty record that silently shows wrong data. Anything else fails with the available names and the closest match.
 */
final class Evaluator
{
    private const int MaxSuggestionDistance = 3;

    /**
     * Injections that only exist when the context carries state.
     */
    private const array StateBound = [Get::class, Set::class, State::class];

    public function __construct(private readonly Container $container)
    {
        Components::getFacadeRoot();
    }

    /**
     * Evaluate a value: closures and Computed values are called, anything else is returned as-is.
     *
     * @param  string  $subject  Describes the value in error messages.
     */
    public function evaluate(mixed $value, EvaluationContext $context, ?ComponentInterface $component = null, string $subject = 'a computed value'): mixed
    {
        if ($value instanceof Computed) {
            $value = $value->closure;
        }

        if (! $value instanceof Closure) {
            return $value;
        }

        $named = $this->namedInjections($context, $component);
        $typed = [...($component === null ? [] : [$component]), ...$this->typedInjections($named), ...$context->typed];
        $arguments = [];

        foreach ((new ReflectionFunction($value))->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $arguments[] = $this->resolveParameter($parameter, $named, $typed, $subject);
        }

        return $value(...$arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function namedInjections(EvaluationContext $context, ?ComponentInterface $component): array
    {
        $named = $context->parameters;

        if ($component !== null) {
            $named['component'] = $component;
        }

        if ($context->state !== null) {
            $named['get'] = new Get($context->state, $context->statePath);
            $named['set'] = new Set($context->state, $context->statePath);

            $name = $component instanceof Component ? $component->name() : null;

            if ($name !== null && ! array_key_exists('state', $named)) {
                $named['state'] = $context->state->peek(StatePath::resolve($context->statePath, $name));
            }
        }

        return $named;
    }

    /**
     * @param  array<string, mixed>  $named
     * @return list<object>
     */
    private function typedInjections(array $named): array
    {
        return array_values(array_filter($named, is_object(...)));
    }

    /**
     * @param  array<string, mixed>  $named
     * @param  list<object>  $typed
     */
    private function resolveParameter(ReflectionParameter $parameter, array $named, array $typed, string $subject): mixed
    {
        $name = $parameter->getName();

        if (array_key_exists($name, $named)) {
            return $named[$name];
        }

        $type = $parameter->getType();
        $class = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

        if ($class !== null) {
            foreach ($typed as $candidate) {
                if ($candidate instanceof $class) {
                    return $candidate;
                }
            }

            if (! in_array($class, self::StateBound, true)
                && ! is_a($class, QueueableEntity::class, true)
                && ($this->container->bound($class) || (class_exists($class) && (new ReflectionClass($class))->isInstantiable()))) {
                return $this->container->make($class);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        $available = array_keys($named);
        sort($available);

        $needsState = in_array($name, ['get', 'set', 'state'], true) || in_array($class, self::StateBound, true);

        throw EvaluationException::unresolvableParameter($subject, $name, $available, $this->closest($name, $available), $needsState);
    }

    /**
     * @param  list<string>  $candidates
     */
    private function closest(string $name, array $candidates): ?string
    {
        $best = null;
        $bestDistance = self::MaxSuggestionDistance + 1;

        foreach ($candidates as $candidate) {
            $distance = levenshtein(strtolower($name), strtolower($candidate));

            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
