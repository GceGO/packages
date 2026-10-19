<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Composition;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Exceptions\BlueprintCompositionException;

/**
 * Optional, per-class composition rules on top of what parts declare.
 *
 * The base Blueprint uses the empty policy: no role is required and every
 * role is allowed, so a blueprint may be a table-only listing, a form-only
 * workflow, a dashboard or anything else. Higher-level packages (for example
 * gcego/modules) tighten it in their Blueprint subclasses.
 */
final readonly class CompositionPolicy
{
    /**
     * @var list<string>
     */
    public array $requiredRoles;

    /**
     * @var list<string>|null
     */
    public ?array $allowedRoles;

    /**
     * @var list<string>
     */
    public array $singleRoles;

    /**
     * @param  list<string>  $requiredRoles  Roles that must have at least one part.
     * @param  list<string>|null  $allowedRoles  Null allows any role.
     * @param  list<string>  $singleRoles  Roles limited to one part regardless of what parts declare.
     */
    public function __construct(
        array $requiredRoles = [],
        ?array $allowedRoles = null,
        array $singleRoles = [],
    ) {
        $this->requiredRoles = self::roles($requiredRoles, 'required');
        $this->allowedRoles = $allowedRoles === null ? null : self::roles($allowedRoles, 'allowed');
        $this->singleRoles = self::roles($singleRoles, 'single');

        if ($this->allowedRoles !== null) {
            $forbidden = array_values(array_diff($this->requiredRoles, $this->allowedRoles));

            if ($forbidden !== []) {
                throw BlueprintCompositionException::invalidPolicy(sprintf('required role(s) %s are not in the allowed roles.', implode(', ', $forbidden)));
            }
        }
    }

    public function withRequiredRoles(string ...$roles): self
    {
        return new self([...$this->requiredRoles, ...array_values($roles)], $this->allowedRoles, $this->singleRoles);
    }

    /**
     * @param  list<string>|null  $roles
     */
    public function withAllowedRoles(?array $roles): self
    {
        return new self($this->requiredRoles, $roles, $this->singleRoles);
    }

    public function withSingleRoles(string ...$roles): self
    {
        return new self($this->requiredRoles, $this->allowedRoles, [...$this->singleRoles, ...array_values($roles)]);
    }

    public function isRequired(string $role): bool
    {
        return in_array($role, $this->requiredRoles, true);
    }

    public function isAllowed(string $role): bool
    {
        return $this->allowedRoles === null || in_array($role, $this->allowedRoles, true);
    }

    public function forcesSingle(string $role): bool
    {
        return in_array($role, $this->singleRoles, true);
    }

    /**
     * @return array{requiredRoles: list<string>, allowedRoles: list<string>|null, singleRoles: list<string>}
     */
    public function toArray(): array
    {
        return ['requiredRoles' => $this->requiredRoles, 'allowedRoles' => $this->allowedRoles, 'singleRoles' => $this->singleRoles];
    }

    /**
     * @param  array<mixed>  $roles
     * @return list<string>
     */
    private static function roles(array $roles, string $kind): array
    {
        $valid = [];

        foreach ($roles as $role) {
            if (! BlueprintRole::isValidName($role) || ! is_string($role)) {
                throw BlueprintCompositionException::invalidPolicy(sprintf('%s roles must be role identifiers such as "table".', $kind));
            }

            $valid[] = $role;
        }

        return array_values(array_unique($valid));
    }
}
