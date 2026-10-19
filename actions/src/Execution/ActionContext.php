<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Tenancy\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a handler receives, all of it trusted:
 *
 * - $action: the resolved definition (blueprint, action, identity);
 * - $authorization, $user, $tenant: the verified security context;
 * - $record: the record, resolved through the scoped data source and authorized;
 * - $input: validated, writable input only;
 * - $selection: for bulk actions, the authorized keys of the whole run;
 * - $effects: redirect, message and after-commit callbacks.
 *
 * Built per execution and never stored; it holds no request object.
 */
final readonly class ActionContext
{
    /**
     * @param  list<string|int>  $selection
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public ResolvedAction $action,
        public AuthorizationContext $authorization,
        public mixed $record = null,
        public ValidatedInput $input = new ValidatedInput,
        public array $selection = [],
        public ActionEffects $effects = new ActionEffects,
        public array $metadata = [],
    ) {}

    public function identity(): string
    {
        return $this->action->identity();
    }

    /**
     * The record as an Eloquent model, for typed handlers: $context->model()->update([...]).
     *
     * @throws InvalidActionException when the action has no Eloquent record.
     */
    public function model(): Model
    {
        return $this->record instanceof Model ? $this->record : throw InvalidActionException::invalid($this->identity(), 'its handler needs an Eloquent record; declare Record true.');
    }

    public function user(): ?Authenticatable
    {
        return $this->authorization->user;
    }

    public function tenant(): ?Tenant
    {
        return $this->authorization->tenant;
    }

    public function withRecord(mixed $record): self
    {
        return new self($this->action, $this->authorization, $record, $this->input, $this->selection, $this->effects, $this->metadata);
    }

    public function withInput(ValidatedInput $input): self
    {
        return new self($this->action, $this->authorization, $this->record, $input, $this->selection, $this->effects, $this->metadata);
    }
}
