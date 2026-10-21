<?php

declare(strict_types=1);

namespace Gcego\Components\Evaluation;

use Closure;
use Gcego\Components\Contracts\RuntimeValue;

/**
 * A property value computed when the component is evaluated.
 *
 * Builders wrap closures passed to fluent methods automatically:
 *
 *     TextInput::make('slug')->disabled(fn (Get $get): bool => $get('locked'))
 *
 * Validation defers the property's type checks until evaluation, where the
 * closure's result is checked against the same property definition.
 * Normalization replaces it with a RuntimePlaceholder.
 */
final readonly class Computed implements RuntimeValue
{
    public function __construct(public Closure $closure) {}
}
