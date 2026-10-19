<?php

declare(strict_types=1);

namespace Gcego\Modules\Relationships;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Permissions\PermissionName;
use Illuminate\Container\Container;

/**
 * Requirement of a relation's pages and relationship actions: the parent
 * record must exist in scope and allow $ability ("view" to read, "update" to
 * create, attach or detach related records).
 *
 * Outside the relation's pages (a link to the relation drawn on the parent's
 * page, a navigation item) there is no parent to check yet, so it allows;
 * on the relation's pages, and in every Livewire update from them, it
 * checks. The records themselves are constrained by ParentScope either way.
 */
final readonly class ParentAccess implements Requirement
{
    public function __construct(public RelationReference $relation, public string $ability = 'view')
    {
        PermissionName::assert($ability, 'ability');
    }

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        $parents = Container::getInstance()->make(ParentRecords::class);

        if ($parents->key($this->relation) === null) {
            return AuthorizationDecision::allow('modules.parent', $resource, ['relation' => $this->relation->identity(), 'parent' => 'outside']);
        }

        [$decision] = $parents->inspect($this->relation, $this->ability);

        return $decision->allowed ? AuthorizationDecision::allow('modules.parent', $resource, ['relation' => $this->relation->identity()]) : $decision;
    }

    public function describe(): string
    {
        return sprintf('parent %s (%s)', $this->relation->identity(), $this->ability);
    }

    /**
     * @return array{relation: string, ability: string}
     */
    public function normalize(): array
    {
        return ['relation' => $this->relation->identity(), 'ability' => $this->ability];
    }
}
