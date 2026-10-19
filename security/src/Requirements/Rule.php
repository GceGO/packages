<?php

declare(strict_types=1);

namespace Gcego\Security\Requirements;

use Closure;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Illuminate\Auth\Access\Response;

/**
 * A trusted custom rule, for conditions no permission or policy expresses:
 *
 *     new Rule('two-factor enabled', fn (AuthorizationContext $context) => $context->user?->two_factor_confirmed_at !== null)
 *
 * Only true (or an allowed Response/decision) allows.
 */
final readonly class Rule implements Requirement
{
    /**
     * @param  Closure(AuthorizationContext, mixed): (bool|Response|AuthorizationDecision)  $rule
     */
    public function __construct(public string $description, public Closure $rule) {}

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        $result = ($this->rule)($context, $resource);

        return match (true) {
            $result instanceof AuthorizationDecision => $result,
            $result instanceof Response => AuthorizationDecision::fromResponse($result, 'rule', $resource, DenialReason::RuleDenied, $engine->strategy()),
            $result === true => AuthorizationDecision::allow('rule', $resource, ['rule' => $this->description]),
            default => AuthorizationDecision::deny('rule', DenialReason::RuleDenied, $resource, $engine->strategy(), metadata: ['rule' => $this->description]),
        };
    }

    public function describe(): string
    {
        return 'rule: '.$this->description;
    }

    /**
     * @return array{rule: string}
     */
    public function normalize(): array
    {
        return ['rule' => $this->description];
    }
}
