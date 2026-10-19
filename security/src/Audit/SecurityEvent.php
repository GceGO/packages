<?php

declare(strict_types=1);

namespace Gcego\Security\Audit;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;

/**
 * A structured security event: identifiers and codes only. Never
 * attributes, request payloads, credentials or personal data.
 */
final readonly class SecurityEvent
{
    public function __construct(
        public SecurityEventType $type,
        public string $ability,
        public ?string $reason,
        public int $status,
        public ?string $resourceType,
        public string|int|null $resourceKey,
        public string $guard,
        public string|int|null $userKey,
        public ?string $tenant,
    ) {}

    public static function fromDecision(SecurityEventType $type, AuthorizationDecision $decision, AuthorizationContext $context): self
    {
        return new self($type, $decision->ability, $decision->reason?->value, $decision->status, $decision->resource->type, $decision->resource->key, $context->guard, $context->userKey(), $context->tenant?->id());
    }

    /**
     * @return array{event: string, ability: string, reason: string|null, status: int, resource_type: string|null, resource_key: string|int|null, guard: string, user: string|int|null, tenant: string|null}
     */
    public function toArray(): array
    {
        return [
            'event' => $this->type->value,
            'ability' => $this->ability,
            'reason' => $this->reason,
            'status' => $this->status,
            'resource_type' => $this->resourceType,
            'resource_key' => $this->resourceKey,
            'guard' => $this->guard,
            'user' => $this->userKey,
            'tenant' => $this->tenant,
        ];
    }
}
