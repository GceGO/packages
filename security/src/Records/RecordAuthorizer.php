<?php

declare(strict_types=1);

namespace Gcego\Security\Records;

use Gcego\Data\DataManager;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Source;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\AccessDeniedException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves one record and authorizes an ability on it, in that order and
 * through gcego/data, so a known identifier never escapes a scope:
 *
 *  1. Scoped lookup: global scopes, soft deletes, the source's constraints
 *     and every security scope (tenant, user, role). Out of scope = missing.
 *  2. The source's requirement nodes, evaluated with the record.
 *  3. The ability: the model's policy (or a Gate ability) for that record.
 *
 * Missing or out-of-scope records always answer 404. A record that is in
 * scope but denied answers 403, or 404 with DenialStrategy::Conceal.
 *
 *     $subject = $records->resolve($source, $id, 'update');
 */
final readonly class RecordAuthorizer
{
    public function __construct(
        private DataManager $data,
        private AuthorizationEngine $engine,
        private Gate $gate,
    ) {}

    /**
     * @throws AccessDeniedException
     */
    public function resolve(Source|SourceDefinition $source, string|int $key, string $ability = 'view', ?DenialStrategy $strategy = null): mixed
    {
        [$decision, $record] = $this->inspect($source, $key, $ability, $strategy);
        $decision->authorize();

        return $record;
    }

    /**
     * @return array{0: AuthorizationDecision, 1: mixed} The decision and, when allowed, the record.
     */
    public function inspect(Source|SourceDefinition $source, string|int $key, string $ability = 'view', ?DenialStrategy $strategy = null): array
    {
        $definition = $this->data->definition($source);
        $context = $this->engine->context();
        $strategy ??= $this->engine->strategy();

        // Data-level authorization is replaced by the explicit checks below; scopes still apply.
        $record = $this->data->find($definition, $key, QueryContext::system())->record();

        if ($record === null) {
            return [AuthorizationDecision::deny($ability, DenialReason::NotFound, $definition->model, DenialStrategy::Conceal), null];
        }

        $requirements = array_values(array_filter($definition->source->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
        $hasAbility = ($record instanceof Model && $this->engine->policyFor($record) !== null) || $this->gate->has($ability);

        if ($requirements === [] && ! $hasAbility) {
            return [AuthorizationDecision::deny($ability, DenialReason::NoPolicy, $record, $strategy), null];
        }

        if ($requirements !== []) {
            $decision = $this->engine->requirements($requirements, $record, $context, $ability);

            if ($decision->denied()) {
                return [$decision->withStrategy($strategy), null];
            }
        }

        $decision = $hasAbility ? $this->engine->ability($ability, $record, $context, $strategy) : AuthorizationDecision::allow($ability, $record);

        return $decision->allowed ? [$decision, $record] : [$decision->withStrategy($strategy), null];
    }
}
