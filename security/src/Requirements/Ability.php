<?php

declare(strict_types=1);

namespace Gcego\Security\Requirements;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Permissions\PermissionName;
use Illuminate\Database\Eloquent\Model;

/**
 * Requires a Laravel Gate ability or policy method:
 *
 *     new Ability('viewAny', Subject::class)   // class-level policy check
 *     new Ability('update')                     // the record being accessed (resource passed at check time)
 *     new Ability('reports.view')               // a Gate::define ability
 *
 * The record passed when checking takes precedence over $model. Undefined
 * abilities and models without a policy are denied.
 */
final readonly class Ability implements Requirement
{
    /**
     * @param  class-string<Model>|null  $model
     */
    public function __construct(public string $ability, public ?string $model = null)
    {
        PermissionName::assert($ability, 'ability');
    }

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        $target = $resource instanceof Model ? $resource : ($this->model ?? $resource);

        return $engine->ability($this->ability, $target, $context);
    }

    public function describe(): string
    {
        return 'ability '.$this->ability.($this->model === null ? '' : ' on '.class_basename($this->model));
    }

    /**
     * @return array{ability: string, model: string|null}
     */
    public function normalize(): array
    {
        return ['ability' => $this->ability, 'model' => $this->model];
    }
}
