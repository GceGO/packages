<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tasks;

use Gcego\Security\Contracts\Requirement;
use Gcego\Workflows\Access\WorkflowAuthorizer;
use Gcego\Workflows\Contracts\AssignmentResolver;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Support\Keys;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns a trusted AssignmentResolver into eligible people: user models
 * that belong to the instance's tenant and satisfy the step's
 * requirements, without duplicates (and without the initiator when
 * self-approval is not allowed). Resolvers cannot widen access.
 */
final readonly class TaskAssigner
{
    public function __construct(private Container $container, private WorkflowAuthorizer $authorizer) {}

    /**
     * @param  class-string<AssignmentResolver>  $resolver
     * @param  list<Requirement>  $requirements
     * @return list<Model>
     */
    public function resolve(string $resolver, WorkflowContext $context, array $requirements, bool $excludeInitiator = false): array
    {
        $instance = $this->container->make($resolver);
        $people = [];

        foreach ($instance instanceof AssignmentResolver ? $instance->resolve($context) : [] as $person) {
            if (! $person instanceof Model || $person->getKey() === null) {
                continue;
            }

            $key = $person->getMorphClass().'#'.Keys::string($person->getKey());

            if (isset($people[$key]) || ($excludeInitiator && $context->instance->initiatedBy($person))) {
                continue;
            }

            if ($this->eligible($person, $context->instance->tenant, $requirements)) {
                $people[$key] = $person;
            }
        }

        return array_values($people);
    }

    /**
     * @param  list<Requirement>  $requirements
     */
    public function eligible(Model $person, ?string $tenant, array $requirements): bool
    {
        return $this->authorizer->memberOf($person, $tenant) && $this->authorizer->satisfies($person, $requirements, $tenant);
    }
}
