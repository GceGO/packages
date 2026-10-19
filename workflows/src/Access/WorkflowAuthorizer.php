<?php

declare(strict_types=1);

namespace Gcego\Workflows\Access;

use Gcego\Security\Contracts\Requirement;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Every workflow decision, deny by default:
 *
 * - people need the subject's Laravel policy ability (a subject without a
 *   policy allows nothing), any gcego/security requirements of the step
 *   (evaluated as that person, in the instance's tenant) and membership of
 *   the instance's tenant;
 * - system actors may run only transitions that name them, or the outcome
 *   transitions the definition itself wires to approvals and tasks;
 * - the "gcego-workflows.manage" Gate ability may manage (cancel, suspend,
 *   resume, reassign) any workflow it can see.
 *
 * Errors deny.
 */
final class WorkflowAuthorizer
{
    /** @var array<string, bool> Tenant memberships checked in this request (scoped instance). */
    private array $memberships = [];

    public function __construct(
        private readonly Gate $gate,
        private readonly SecurityManager $security,
        private readonly SecurityConfig $securityConfig,
        private readonly TenantAuthorizer $tenants,
        private readonly WorkflowsConfig $config,
    ) {}

    public function canStart(Workflow $workflow, Model $subject, Model $user, ?string $tenant): bool
    {
        return $this->memberOf($user, $tenant)
            && $this->policyAllows($user, $workflow->startAbility(), $subject)
            && $this->satisfies($user, $workflow->requirements(), $tenant);
    }

    /**
     * Null when allowed, else a reason code ("ability", "system", "requirements", "tenant").
     */
    public function transitionDenial(Workflow $workflow, Transition $transition, WorkflowInstance $instance, ?Model $subject, Model|SystemActor $actor): ?string
    {
        if ($actor instanceof SystemActor) {
            return $this->systemMay($workflow, $transition, $actor) ? null : 'system';
        }

        if (! $transition->allowsHumans()) {
            return 'system';
        }

        if (! $this->memberOf($actor, $instance->tenant)) {
            return 'tenant';
        }

        if ($subject === null || ! $this->policyAllows($actor, $transition->ability(), $subject)) {
            return 'ability';
        }

        return $this->satisfies($actor, $transition->requirements(), $instance->tenant) ? null : 'requirements';
    }

    public function canView(Workflow $workflow, WorkflowInstance $instance, ?Model $subject, Model $user): bool
    {
        if (! $this->memberOf($user, $instance->tenant)) {
            return false;
        }

        if ($this->managesAll($user) || ($subject !== null && $this->policyAllows($user, $workflow->viewAbility(), $subject))) {
            return true;
        }

        // Reviewers see the workflows they have work in.
        return $instance->tasks()->where('assignee_type', $user->getMorphClass())->where('assignee_id', Keys::string($user->getKey()))->exists();
    }

    public function canManage(Workflow $workflow, WorkflowInstance $instance, ?Model $subject, Model $user): bool
    {
        return $this->memberOf($user, $instance->tenant)
            && ($this->managesAll($user) || ($subject !== null && $this->policyAllows($user, $workflow->manageAbility(), $subject)));
    }

    /**
     * @param  list<Requirement>  $requirements
     */
    public function satisfies(Model $user, array $requirements, ?string $tenant): bool
    {
        if ($requirements === []) {
            return true;
        }

        if (! $user instanceof Authenticatable) {
            return false;
        }

        try {
            return $this->security->actingAs($user, $tenant, fn (): bool => ! $this->security->check(...$requirements)->denied());
        } catch (Throwable) {
            return false;
        }
    }

    public function memberOf(Model $user, ?string $tenant): bool
    {
        if ($tenant === null || ! $this->securityConfig->tenancy) {
            return true;
        }

        if (! $user instanceof Authenticatable) {
            return false;
        }

        $key = $user->getMorphClass().'#'.Keys::string($user->getKey()).'@'.$tenant;

        if (isset($this->memberships[$key])) {
            return $this->memberships[$key];
        }

        try {
            $this->tenants->verify($user, $tenant, $this->securityConfig->guard ?? 'web');

            return $this->memberships[$key] = true;
        } catch (Throwable) {
            return $this->memberships[$key] = false;
        }
    }

    public function managesAll(Model $user): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        $gate = $this->gate->forUser($user);

        return $gate->has($this->config->manageAbility) && $gate->allows($this->config->manageAbility);
    }

    private function systemMay(Workflow $workflow, Transition $transition, SystemActor $actor): bool
    {
        if (in_array($actor->name, $transition->systemActors(), true)) {
            return true;
        }

        $name = $transition->nodeName();

        return match ($actor->name) {
            SystemActor::Approvals => array_filter($workflow->approvals(), static fn ($approval): bool => in_array($name, [$approval->onApprove(), $approval->onReject(), $approval->onChanges()], true)) !== [],
            SystemActor::Tasks => array_filter($workflow->states(), static fn ($state): bool => array_filter($state->tasks(), static fn ($task): bool => $task->completes() === $name) !== []) !== [],
            default => false,
        };
    }

    private function policyAllows(Model $user, string $ability, Model $subject): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        try {
            $gate = $this->gate->forUser($user);

            return $gate->getPolicyFor($subject) !== null && $gate->allows($ability, $subject);
        } catch (Throwable) {
            return false;
        }
    }
}
