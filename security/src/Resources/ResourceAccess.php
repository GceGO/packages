<?php

declare(strict_types=1);

namespace Gcego\Security\Resources;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\Permissions\PermissionName;
use Illuminate\Database\Eloquent\Model;

/**
 * Conventional access to a module's model (subjects, topics, students, ...):
 *
 *     new ResourceAccess(Subject::class)                            // the model's policy
 *     new ResourceAccess(Subject::class, permission: 'subjects')    // + subjects.view_any, subjects.update, ...
 *     new ResourceAccess(Member::class, permission: 'members', tenant: true, permissions: ['viewAny' => 'members.view'])
 *
 * Each ability is allowed only when every configured check allows: the
 * model's policy (when one is registered) and the mapped permission (when
 * a prefix or override is given). With neither, it is denied: registering
 * a module never grants access. As a requirement on a blueprint it
 * protects module access with viewAny.
 */
final readonly class ResourceAccess implements Requirement
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, string>  $abilities  Ability => policy method override ('update' => 'edit').
     * @param  array<string, string>  $permissions  Ability => permission override ('viewAny' => 'subjects.view').
     */
    public function __construct(
        public string $model,
        public ?string $permission = null,
        public bool $tenant = false,
        public array $abilities = [],
        public array $permissions = [],
    ) {
        if (! is_subclass_of($model, Model::class)) {
            throw InvalidPermissionException::invalid('ResourceAccess', sprintf('%s is not an Eloquent model.', $model));
        }

        if ($permission !== null) {
            PermissionName::assert($permission, 'permission prefix');
        }

        foreach ([...array_values($abilities), ...array_values($permissions)] as $name) {
            PermissionName::assert($name);
        }
    }

    public function check(AuthorizationEngine $engine, AuthorizationContext $context, ResourceAbility|string $ability, ?Model $record = null): AuthorizationDecision
    {
        $key = $ability instanceof ResourceAbility ? $ability->value : $ability;
        $conventional = $ability instanceof ResourceAbility ? $ability : ResourceAbility::tryFrom($ability);

        if ($conventional?->needsRecord() === true && ! $record instanceof $this->model) {
            throw InvalidPermissionException::invalid('ResourceAccess', sprintf('"%s" needs a %s record.', $key, class_basename($this->model)));
        }

        $policyMethod = $this->abilities[$key] ?? $key;
        $permission = $this->permissions[$key] ?? ($this->permission === null ? null : $this->permission.'.'.($conventional?->permissionSuffix() ?? $key));
        $hasPolicy = $engine->policyFor($this->model) !== null;

        if (! $hasPolicy && $permission === null) {
            return AuthorizationDecision::deny($key, DenialReason::NoPolicy, $record ?? $this->model, $engine->strategy(), metadata: ['model' => $this->model]);
        }

        if ($permission !== null) {
            $decision = $engine->permission($permission, $context, $this->tenant);

            if ($decision->denied()) {
                return $decision;
            }
        }

        return $hasPolicy
            ? $engine->ability($policyMethod, $record ?? $this->model, $context)
            : AuthorizationDecision::allow($key, $record ?? $this->model, ['permission' => $permission]);
    }

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        return $resource instanceof $this->model
            ? $this->check($engine, $context, ResourceAbility::View, $resource)
            : $this->check($engine, $context, ResourceAbility::ViewAny);
    }

    public function describe(): string
    {
        return sprintf('resource %s%s', class_basename($this->model), $this->permission === null ? '' : ' (permissions '.$this->permission.'.*)');
    }

    /**
     * @return array{model: string, permission: string|null, tenant: bool}
     */
    public function normalize(): array
    {
        return ['model' => $this->model, 'permission' => $this->permission, 'tenant' => $this->tenant];
    }
}
