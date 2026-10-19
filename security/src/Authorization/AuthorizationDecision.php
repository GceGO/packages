<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

use Gcego\Security\Exceptions\AccessDeniedException;
use Illuminate\Auth\Access\Response;

/**
 * The immutable result of one authorization check.
 *
 *     $decision = $security->authorize('update', $subject);
 *     $decision->allowed;      // bool
 *     $decision->reason;       // DenialReason|null
 *     $decision->status;       // 403 or 404 (denials), 200 (allowed)
 *     $decision->authorize();  // throws AccessDeniedException when denied
 *
 * $message is public: a policy's own denial message, or a generic one. It
 * never contains user, tenant or record details; $reason and $metadata
 * carry the developer-facing explanation.
 */
final readonly class AuthorizationDecision
{
    public const string DefaultMessage = 'This action is unauthorized.';

    /**
     * @param  array<string, scalar|list<scalar>|null>  $metadata  Safe details (requirement names, permission names), never values or personal data.
     */
    private function __construct(
        public bool $allowed,
        public string $ability,
        public ResourceReference $resource,
        public ?DenialReason $reason = null,
        public int $status = 200,
        private ?string $publicMessage = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, scalar|list<scalar>|null>  $metadata
     */
    public static function allow(string $ability, mixed $resource = null, array $metadata = []): self
    {
        return new self(true, $ability, $resource instanceof ResourceReference ? $resource : ResourceReference::of($resource), metadata: $metadata);
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $metadata
     */
    public static function deny(string $ability, DenialReason $reason, mixed $resource = null, DenialStrategy|int $status = DenialStrategy::Forbidden, ?string $message = null, array $metadata = []): self
    {
        return new self(
            false,
            $ability,
            $resource instanceof ResourceReference ? $resource : ResourceReference::of($resource),
            $reason,
            $status instanceof DenialStrategy ? $status->value : $status,
            $message,
            $metadata,
        );
    }

    /**
     * From a Laravel Gate/policy Response, keeping a policy's own message and status (denyAsNotFound()).
     */
    public static function fromResponse(Response $response, string $ability, mixed $resource, DenialReason $reason, DenialStrategy $strategy): self
    {
        return $response->allowed()
            ? self::allow($ability, $resource)
            : self::deny($ability, $reason, $resource, $response->status() ?? $strategy->value, $response->message());
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * The same decision answered with another strategy (for example 404 for out-of-scope records).
     */
    public function withStrategy(DenialStrategy $strategy): self
    {
        return $this->allowed ? $this : new self(false, $this->ability, $this->resource, $this->reason, $strategy->value, $strategy === DenialStrategy::Conceal ? null : $this->publicMessage, $this->metadata);
    }

    public function message(): string
    {
        if ($this->allowed) {
            return '';
        }

        if ($this->status === DenialStrategy::Conceal->value) {
            return 'Not found.';
        }

        return $this->publicMessage ?? self::DefaultMessage;
    }

    /**
     * @throws AccessDeniedException
     */
    public function authorize(): self
    {
        if ($this->denied()) {
            throw AccessDeniedException::fromDecision($this);
        }

        return $this;
    }

    public function toResponse(): Response
    {
        return $this->allowed ? Response::allow() : Response::deny($this->message())->withStatus($this->status);
    }

    /**
     * @return array{allowed: bool, ability: string, resource: string, reason: string|null, status: int, message: string, metadata: array<string, scalar|list<scalar>|null>}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'ability' => $this->ability,
            'resource' => $this->resource->toString(),
            'reason' => $this->reason?->value,
            'status' => $this->status,
            'message' => $this->message(),
            'metadata' => $this->metadata,
        ];
    }
}
