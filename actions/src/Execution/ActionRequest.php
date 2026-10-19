<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Gcego\Actions\Exceptions\InvalidActionException;

/**
 * One execution request. Everything here except $definition (chosen by
 * trusted server code) is untrusted: the action name only selects among the
 * definition's own actions, keys are re-resolved through scoped data, input
 * is validated, and the confirmation token is verified.
 */
final readonly class ActionRequest
{
    /**
     * @param  string  $definition  A registered blueprint identity ("blueprint:subjects").
     * @param  list<string|int>  $selection  Bulk selection keys.
     * @param  array<array-key, mixed>  $input  Submitted values.
     */
    public function __construct(
        public string $definition,
        public string $action,
        public string|int|null $record = null,
        public array $selection = [],
        public array $input = [],
        public ?string $confirmation = null,
        public ?string $idempotencyKey = null,
    ) {
        if ($idempotencyKey !== null && preg_match('/^[A-Za-z0-9_\-:.]{8,128}$/', $idempotencyKey) !== 1) {
            throw InvalidActionException::invalid('request', 'the idempotency key must be 8 to 128 letters, digits or "-_:.".');
        }
    }

    public function withConfirmation(string $token): self
    {
        return new self($this->definition, $this->action, $this->record, $this->selection, $this->input, $token, $this->idempotencyKey);
    }
}
