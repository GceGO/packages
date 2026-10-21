<?php

declare(strict_types=1);

namespace Gcego\Security\Resources;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Contracts\Requirement;
use Throwable;

/**
 * Authorizes access to definitions: blueprints today; panels, pages,
 * modules and actions as their packages arrive. Requirements are the
 * Requirement configuration nodes on the target component and every
 * ancestor: all must allow, and a path without any is denied.
 *
 *     $definitions->identity('blueprint:subjects');                     // via gcego/registry
 *     $definitions->authorize($blueprint, 'subjects.delete-action', $record);
 *     $definitions->bulk($blueprint, 'subjects.bulk-delete', $records);
 */
final readonly class DefinitionAuthorizer
{
    public function __construct(
        private AuthorizationEngine $engine,
        private ?RegistryInterface $registry = null,
    ) {}

    public function authorize(ComponentInterface $definition, ?string $path = null, mixed $resource = null, ?AuthorizationContext $context = null, string $ability = 'access'): AuthorizationDecision
    {
        $requirements = $this->requirements($definition, $path);

        if ($requirements === null) {
            return AuthorizationDecision::deny($ability, DenialReason::NotFound, $resource, DenialStrategy::Conceal, metadata: ['path' => $path]);
        }

        return $this->engine->requirements($requirements, $resource, $context, $ability);
    }

    /**
     * A registered definition by identity ("blueprint:subjects", "subjects"). Unknown identities are 404.
     */
    public function identity(string $identity, ?string $path = null, mixed $resource = null, ?AuthorizationContext $context = null): AuthorizationDecision
    {
        try {
            $definition = $this->registry?->get($identity);
        } catch (Throwable) {
            $definition = null;
        }

        if (! $definition instanceof ComponentInterface) {
            return AuthorizationDecision::deny('access', DenialReason::NotFound, null, DenialStrategy::Conceal);
        }

        return $this->authorize($definition, $path, $resource, $context);
    }

    /**
     * A bulk operation is allowed only when it is allowed for every record.
     *
     * @param  iterable<mixed>  $records
     */
    public function bulk(ComponentInterface $definition, ?string $path, iterable $records, ?AuthorizationContext $context = null): AuthorizationDecision
    {
        $context ??= $this->engine->context();
        $count = 0;

        foreach ($records as $record) {
            $decision = $this->authorize($definition, $path, $record, $context);
            $count++;

            if ($decision->denied()) {
                return AuthorizationDecision::deny('bulk', $decision->reason ?? DenialReason::PolicyDenied, $record, $decision->status, metadata: ['checked' => $count]);
            }
        }

        return $count === 0
            ? AuthorizationDecision::deny('bulk', DenialReason::NotFound, null, DenialStrategy::Conceal)
            : AuthorizationDecision::allow('bulk', null, ['records' => $count]);
    }

    /**
     * Requirements from the root to the component at $path, or null when the path does not exist.
     *
     * @return list<Requirement>|null
     */
    public function requirements(ComponentInterface $definition, ?string $path = null): ?array
    {
        $nodes = [];
        $root = null;

        foreach (app(ComponentWalker::class)->preOrder($definition) as $node) {
            $root ??= $node;
            $nodes[$node->path->toString()] ??= $node;
        }

        $target = $path === null ? $root : ($nodes[$path] ?? null);
        $chain = [];

        for ($node = $target; $node !== null; $node = $node->parentPath === null ? null : ($nodes[$node->parentPath->toString()] ?? null)) {
            array_unshift($chain, $node);
        }

        if ($target === null) {
            return null;
        }

        $requirements = [];

        foreach ($chain as $node) {
            foreach ($node->component->configurations() as $configuration) {
                if ($configuration instanceof Requirement) {
                    $requirements[] = $configuration;
                }
            }
        }

        return $requirements;
    }
}
