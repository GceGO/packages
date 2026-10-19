<?php

declare(strict_types=1);

namespace Gcego\Actions\Contracts;

use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Execution\ValidatedInput;
use Gcego\Actions\Resolution\ResolvedAction;
use Illuminate\Validation\ValidationException;

/**
 * Turns untrusted submitted values into validated, writable input.
 *
 * gcego/actions ships RulesInputResolver (actions declaring an Input node);
 * gcego/forms registers its own for actions that carry a form schema:
 *
 *     $this->app->make(ActionManager::class)->inputResolver(new FormInputResolver(...));
 *
 * Resolvers are asked in registration order, most recent first.
 */
interface InputResolver
{
    public function supports(ResolvedAction $action): bool;

    /**
     * @param  array<array-key, mixed>  $submitted  Untrusted values (Livewire state, request payload).
     *
     * @throws ValidationException
     */
    public function resolve(ResolvedAction $action, ActionContext $context, array $submitted): ValidatedInput;

    /**
     * Initial values for an input dialog (for example the record's current attributes), readable fields only.
     *
     * @return array<string, mixed>
     */
    public function initial(ResolvedAction $action, ActionContext $context): array;
}
