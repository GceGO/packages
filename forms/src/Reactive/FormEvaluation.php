<?php

declare(strict_types=1);

namespace Gcego\Forms\Reactive;

use Closure;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Evaluation\State;
use Gcego\Components\Facades\Components;
use Gcego\Forms\State\FormState;

/**
 * Runs Filament-style closures (fn (Get $get, ?Model $record, string $operation) => ...)
 * against form state, per request.
 *
 * The record and operation come from the innermost within() call. While
 * discover() runs, the state paths closures read are collected, which is how
 * fields become live without dependsOn.
 */
final class FormEvaluation
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $scopes = [];

    /**
     * Template paths read while discover() runs ("objectives.*.title").
     *
     * @var list<array<string, true>>
     */
    private array $discoveries = [];

    /**
     * @template T
     *
     * @param  array<string, mixed>  $parameters  Injected by name: record, operation, ...
     * @param  Closure(): T  $callback
     * @return T
     */
    public function within(array $parameters, Closure $callback): mixed
    {
        $this->scopes[] = $parameters;

        try {
            return $callback();
        } finally {
            array_pop($this->scopes);
        }
    }

    /**
     * Run the callback and return the template paths closures read meanwhile.
     *
     * @param  Closure(): mixed  $callback
     * @return list<string>
     */
    public function discover(Closure $callback): array
    {
        $this->discoveries[] = [];
        $key = array_key_last($this->discoveries);

        try {
            $callback();
        } finally {
            $paths = array_keys($this->discoveries[$key]);
            array_pop($this->discoveries);
        }

        return array_map(strval(...), $paths);
    }

    /**
     * Call a closure with $get, $set, $record, $operation and typed injection.
     * Inside a repeater, relative paths start at the item.
     *
     * @param  array<string, mixed>  $parameters  Extra named injections ($state, $old, ...).
     */
    public function evaluate(Closure $closure, FormState|State $state, ?string $itemPath = null, array $parameters = []): mixed
    {
        $componentState = $state instanceof State ? $state : new State($state->all());
        $context = EvaluationContext::make([...($this->scopes === [] ? [] : $this->scopes[array_key_last($this->scopes)]), ...$parameters])
            ->withState($componentState, $itemPath ?? '');

        $recording = $componentState->record(static fn (): mixed => Components::evaluator()->evaluate($closure, $context, subject: 'a form closure'));

        foreach (array_keys($this->discoveries) as $index) {
            foreach ($recording['reads'] as $path) {
                $this->discoveries[$index][self::template($path)] = true;
            }
        }

        return $recording['result'];
    }

    /**
     * "objectives.i1a2b3c4d5e.title" to "objectives.*.title".
     */
    public static function template(string $path): string
    {
        return (string) preg_replace('/(^|\.)i[a-f0-9]{10}(?=\.|$)/', '$1*', $path);
    }
}
