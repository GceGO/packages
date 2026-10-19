<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Composition;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Validation\BlueprintValidationError;

/**
 * The immutable classification of a blueprint's parts.
 *
 * Built once per blueprint instance by BlueprintComposer. Composition
 * problems are recorded as issues rather than thrown, so validation can
 * report all of them with paths; strict lookups on Blueprint throw.
 */
final readonly class BlueprintComposition
{
    /**
     * @param  array<string, BlueprintPartCollection>  $byRole  In order of first appearance.
     * @param  array<string, BlueprintRole>  $roles  Effective cardinality per present role.
     * @param  list<BlueprintValidationError>  $issues
     */
    public function __construct(
        private BlueprintPartCollection $parts,
        private array $byRole,
        private array $roles,
        private array $issues,
    ) {}

    /**
     * Every part, in document order.
     */
    public function parts(): BlueprintPartCollection
    {
        return $this->parts;
    }

    public function ofRole(string $role): BlueprintPartCollection
    {
        return $this->byRole[$role] ?? new BlueprintPartCollection;
    }

    public function has(string $role): bool
    {
        return isset($this->byRole[$role]);
    }

    /**
     * Present roles, in order of first appearance.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return array_keys($this->byRole);
    }

    /**
     * The effective cardinality of a present role, or null when no part plays it.
     */
    public function role(string $role): ?BlueprintRole
    {
        return $this->roles[$role] ?? null;
    }

    /**
     * @return list<BlueprintValidationError>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(static fn (BlueprintValidationError $issue): string => $issue->message, $this->issues);
    }
}
