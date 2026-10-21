<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

use Gcego\Blueprints\Contracts\BlueprintInterface;

/**
 * Integration validation: checks a blueprint's class references against the
 * installed ReferenceRule adapters.
 *
 * Structural validation already happened in Blueprint::define(), so this runs
 * where references may be autoloaded: when gcego/registry registers or
 * resolves the blueprint. For each reference the rules run in order and the
 * first failure is reported; a reference that does not exist is not checked further.
 * A ReferenceRules set is locked on first use.
 */
final readonly class BlueprintValidator
{
    /**
     * @param  iterable<ReferenceRule>  $rules  Iterated on every validation, so a live ReferenceRules sees later additions.
     */
    public function __construct(
        private iterable $rules = new ReferenceRules,
    ) {}

    public static function withDefaults(): self
    {
        return new self(ReferenceRules::defaults());
    }

    public function validate(BlueprintInterface $blueprint): BlueprintValidationResult
    {
        if ($this->rules instanceof ReferenceRules) {
            $this->rules->lock();
        }

        $errors = [];

        foreach ($blueprint->references() as $key => $classes) {
            foreach ($classes as $class) {
                foreach ($this->rules as $rule) {
                    if (! $rule->appliesTo($key)) {
                        continue;
                    }

                    $message = $rule->check($key, $class);

                    if ($message !== null) {
                        $errors[] = new BlueprintValidationError($rule->code(), $message, $blueprint->name(), $key, received: $class);

                        break;
                    }
                }
            }
        }

        return new BlueprintValidationResult($blueprint->name(), ...$errors);
    }
}
