<?php

declare(strict_types=1);

namespace Gcego\Components\Evaluation;

/**
 * What a computed value may ask for.
 *
 * Named parameters are injected by parameter name ($record, $operation,
 * $livewire, ...), typed values by parameter type. When state is supplied,
 * $get, $set and $state are injected as well.
 *
 *     EvaluationContext::make(['record' => $user, 'operation' => 'edit'])
 *         ->withState(['data' => $data], 'data')
 */
final readonly class EvaluationContext
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<object>  $typed
     */
    public function __construct(
        public array $parameters = [],
        public array $typed = [],
        public ?State $state = null,
        public string $statePath = '',
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function make(array $parameters = []): self
    {
        return new self($parameters);
    }

    /**
     * @param  self|array<string, mixed>  $context
     */
    public static function from(self|array $context): self
    {
        return $context instanceof self ? $context : new self($context);
    }

    public function with(string $name, mixed $value): self
    {
        return new self([...$this->parameters, $name => $value], $this->typed, $this->state, $this->statePath);
    }

    public function withTyped(object ...$values): self
    {
        return new self($this->parameters, [...$this->typed, ...array_values($values)], $this->state, $this->statePath);
    }

    /**
     * @param  State|array<array-key, mixed>  $state
     * @param  string  $statePath  The container path relative paths start from.
     */
    public function withState(State|array $state, string $statePath = ''): self
    {
        return new self($this->parameters, $this->typed, $state instanceof State ? $state : new State($state), $statePath);
    }

    /**
     * The current operation (create, edit, view, ...), used by hiddenOn() and visibleOn().
     */
    public function operation(): ?string
    {
        $operation = $this->parameters['operation'] ?? null;

        return is_string($operation) ? $operation : null;
    }
}
