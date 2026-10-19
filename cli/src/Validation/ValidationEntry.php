<?php

declare(strict_types=1);

namespace Gcego\Cli\Validation;

/**
 * The validation outcome of one definition (or of a file that failed to register).
 */
final readonly class ValidationEntry
{
    /**
     * @param  list<ValidationProblem>  $problems
     */
    public function __construct(public ?string $identity, public ?string $kind, public string $source, public array $problems = []) {}

    public function valid(): bool
    {
        return $this->problems === [];
    }

    /**
     * @return array{identity: string|null, kind: string|null, source: string, valid: bool, problems: list<array{code: string, message: string}>}
     */
    public function toArray(): array
    {
        return ['identity' => $this->identity, 'kind' => $this->kind, 'source' => $this->source, 'valid' => $this->valid(), 'problems' => array_map(static fn (ValidationProblem $problem): array => $problem->toArray(), $this->problems)];
    }
}
