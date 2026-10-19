<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\State\FormState;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Additional Laravel validation rules for a field, declared in code:
 *
 *     new Rules('alpha_dash', 'unique:subjects,code', new Uppercase)
 *     new Rules(fn (?Subject $record) => [Rule::unique('subjects', 'code')->ignore($record)])
 *
 * A closure receives the record being edited (null when creating) and the FormState.
 */
final readonly class Rules implements ConfigurationNode
{
    /**
     * @var list<string|ValidationRule|object|Closure>
     */
    public array $rules;

    public function __construct(string|object ...$rules)
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && ($rule === '' || str_contains($rule, '|'))) {
                throw InvalidFieldException::invalid('Rules', 'pass one rule per argument, without "|".');
            }
        }

        $this->rules = array_values($rules);
    }

    /**
     * @return list<mixed>
     */
    public function resolve(mixed $record, FormState $state): array
    {
        $resolved = [];

        foreach ($this->rules as $rule) {
            if ($rule instanceof Closure) {
                $result = $rule($record, $state);
                array_push($resolved, ...(is_array($result) ? array_values($result) : [$result]));

                continue;
            }

            $resolved[] = $rule;
        }

        return $resolved;
    }

    /**
     * @return array{rules: list<string>}
     */
    public function normalize(): array
    {
        return ['rules' => array_map(static fn (mixed $rule): string => is_string($rule) ? $rule : ($rule instanceof Closure ? 'closure' : $rule::class), $this->rules)];
    }
}
