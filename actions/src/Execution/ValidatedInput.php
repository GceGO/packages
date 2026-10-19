<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Input that passed validation and write authorization: the only values handlers persist.
 * Only an InputResolver creates it.
 *
 * $afterSave holds trusted callbacks of the resolver for values that are not
 * model attributes (gcego/media uploads): the engine runs them with the
 * saved record right after the handler returns it, inside the action's
 * transaction, so a failure rolls the whole action back.
 */
final readonly class ValidatedInput
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<Closure(Model): void>  $afterSave
     */
    public function __construct(public array $values = [], public array $afterSave = []) {}

    public static function none(): self
    {
        return new self;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->values[$field] ?? $default;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
