<?php

declare(strict_types=1);

namespace Gcego\Data\Query;

use Closure;
use Gcego\Data\Contracts\QueryConstraint;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Trusted, per-call execution context, created by server code (never from request input).
 *
 *     QueryContext::for($user)->constrain(new InstitutionDataScope($institution))
 *
 * - $user: who is reading. Null uses Laravel's current user (Gate default).
 * - $constraints: request-scoped mandatory constraints, applied after the
 *   source's own and the application's registered scopes.
 * - $authorize: false only for trusted system code (jobs, console); see system().
 *
 * It is a value object passed down a single call; nothing is stored in shared services.
 */
final readonly class QueryContext
{
    /**
     * @param  list<QueryConstraint>  $constraints
     * @param  array<string, scalar|null>  $metadata  Caller information for custom providers and authorizers.
     */
    public function __construct(
        public ?Authenticatable $user = null,
        public array $constraints = [],
        public bool $authorize = true,
        public array $metadata = [],
    ) {}

    public static function for(?Authenticatable $user): self
    {
        return new self($user);
    }

    /**
     * Skips authorization. Mandatory constraints and scopes still apply.
     * Use only in code without an end user, such as queued jobs and console commands.
     */
    public static function system(): self
    {
        return new self(authorize: false);
    }

    public function constrain(QueryConstraint|Closure ...$constraints): self
    {
        $added = array_map(static fn (QueryConstraint|Closure $constraint): QueryConstraint => $constraint instanceof Closure ? new Query($constraint) : $constraint, array_values($constraints));

        return new self($this->user, [...$this->constraints, ...$added], $this->authorize, $this->metadata);
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->user, $this->constraints, $this->authorize, [...$this->metadata, ...$metadata]);
    }
}
