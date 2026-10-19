<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use BackedEnum;
use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Forms\State\FormState;

/**
 * Base of VisibleWhen, RequiredWhen and DisabledWhen:
 *
 *     new VisibleWhen('category', 'science')
 *     VisibleWhen::filled('code')
 *     RequiredWhen::using(fn (FormState $state) => $state->get('level') > 2, dependsOn: ['level'])
 */
abstract readonly class FieldCondition implements ConfigurationNode
{
    public Condition $condition;

    /**
     * @param  string|Condition  $field  A field name, or a prepared Condition.
     * @param  scalar|BackedEnum|list<scalar|BackedEnum>|null  $value
     */
    final public function __construct(string|Condition $field, mixed $value = true)
    {
        $this->condition = $field instanceof Condition ? $field : Condition::make($field, $value);
    }

    public static function filled(string $field): static
    {
        return new static(Condition::filled($field));
    }

    public static function blank(string $field): static
    {
        return new static(Condition::blank($field));
    }

    /**
     * @param  scalar|BackedEnum|list<scalar|BackedEnum>|null  $value
     */
    public static function not(string $field, mixed $value): static
    {
        return new static(Condition::not($field, $value));
    }

    /**
     * @param  Closure(FormState, ?string): bool  $callback
     * @param  list<string>  $dependsOn
     */
    public static function using(Closure $callback, array $dependsOn = []): static
    {
        return new static(Condition::using($callback, $dependsOn));
    }

    /**
     * @return array{when: string}
     */
    public function normalize(): array
    {
        return ['when' => $this->condition->describe()];
    }
}
