<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Forms\State\FormState;
use Illuminate\Contracts\Validation\Validator;

/**
 * Form-level, cross-field validation after the field rules:
 *
 *     new Validate(function (Validator $validator, FormState $state): void {
 *         if ($state->get('starts_on') > $state->get('ends_on')) {
 *             $validator->errors()->add('ends_on', 'The end must be after the start.');
 *         }
 *     })
 */
final readonly class Validate implements ConfigurationNode
{
    /**
     * @param  Closure(Validator, FormState, mixed): void  $after
     */
    public function __construct(public Closure $after) {}

    /**
     * @return array{validate: string}
     */
    public function normalize(): array
    {
        return ['validate' => 'closure'];
    }
}
