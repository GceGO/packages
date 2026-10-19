<?php

declare(strict_types=1);

namespace Gcego\Actions\Input;

use Gcego\Actions\Configuration\Input;
use Gcego\Actions\Contracts\InputResolver;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Execution\ValidatedInput;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Security\Fields\FieldAuthorizer;
use Gcego\Security\Fields\FieldDecisions;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;

/**
 * Validated input from an action's Input node (Laravel validation rules).
 *
 * - Only rule keys are writable; any other submitted key is a 403 denial.
 * - Values are validated with the rules; only validated values are returned.
 * - gcego/security FieldRule nodes on the action restrict per-user writes
 *   (non-editable fields are a 403 denial) and readable initial values.
 */
final readonly class RulesInputResolver implements InputResolver
{
    public function __construct(
        private ValidatorFactory $validators,
        private SecurityManager $security,
        private FieldAuthorizer $fields,
    ) {}

    public function supports(ResolvedAction $action): bool
    {
        return $action->action->configurations()->has(Input::class);
    }

    public function resolve(ResolvedAction $action, ActionContext $context, array $submitted): ValidatedInput
    {
        $input = $this->input($action);
        $decisions = $this->decisions($action, $input, $context->record);

        // Undeclared and non-writable keys are refused before validation runs.
        $this->fields->writable($submitted, $decisions);

        $validator = $this->validators->make($submitted, $input->rules, $input->messages, $input->labels);

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        return new ValidatedInput($validated);
    }

    public function initial(ResolvedAction $action, ActionContext $context): array
    {
        if (! is_object($context->record)) {
            return [];
        }

        $input = $this->input($action);
        $readable = $this->fields->visible($context->record, $this->decisions($action, $input, $context->record));

        return array_intersect_key($readable, array_flip($this->decisions($action, $input, $context->record)->editable()));
    }

    private function input(ResolvedAction $action): Input
    {
        $input = $action->action->configurations()->first(Input::class);

        return $input instanceof Input ? $input : throw new \LogicException('RulesInputResolver needs an Input node.');
    }

    private function decisions(ResolvedAction $action, Input $input, mixed $record): FieldDecisions
    {
        $rules = [];

        foreach ($action->action->configurations()->ofClass(FieldRule::class) as $rule) {
            $rules[$rule->field] = $rule;
        }

        return $this->security->fields(array_map(static fn (string $field): string|FieldRule => $rules[$field] ?? $field, $input->fields()), $record);
    }
}
