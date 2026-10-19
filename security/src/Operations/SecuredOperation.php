<?php

declare(strict_types=1);

namespace Gcego\Security\Operations;

use Gcego\Security\Exceptions\MissingAuthorizationContextException;

/**
 * What a queued job, export or download needs to re-authorize later:
 * identifiers only, never a permission snapshot. Issued by
 * OperationAuthorizer::issue() after the request-time check; checked again
 * by run() (at execution) and authorizeDownload() (on download).
 *
 * Safe to serialize into job payloads (toArray()/fromArray()).
 */
final readonly class SecuredOperation
{
    public function __construct(
        public string $id,
        public string $ability,
        public string $guard,
        public string|int|null $userKey,
        public ?string $tenant = null,
        public ?string $resourceType = null,
        public string|int|null $resourceKey = null,
        public ?string $definition = null,
        public ?string $path = null,
        public int $issuedAt = 0,
    ) {}

    /**
     * @return array{id: string, ability: string, guard: string, user: string|int|null, tenant: string|null, resource_type: string|null, resource_key: string|int|null, definition: string|null, path: string|null, issued_at: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ability' => $this->ability,
            'guard' => $this->guard,
            'user' => $this->userKey,
            'tenant' => $this->tenant,
            'resource_type' => $this->resourceType,
            'resource_key' => $this->resourceKey,
            'definition' => $this->definition,
            'path' => $this->path,
            'issued_at' => $this->issuedAt,
        ];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): ?string => isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
        $key = static fn (string $name): string|int|null => isset($data[$name]) && (is_string($data[$name]) || is_int($data[$name])) ? $data[$name] : null;

        return new self(
            id: $string('id') ?? throw MissingAuthorizationContextException::because('The operation has no id.'),
            ability: $string('ability') ?? throw MissingAuthorizationContextException::because('The operation has no ability.'),
            guard: $string('guard') ?? throw MissingAuthorizationContextException::because('The operation has no guard.'),
            userKey: $key('user'),
            tenant: $string('tenant'),
            resourceType: $string('resource_type'),
            resourceKey: $key('resource_key'),
            definition: $string('definition'),
            path: $string('path'),
            issuedAt: isset($data['issued_at']) && is_int($data['issued_at']) ? $data['issued_at'] : 0,
        );
    }

    /**
     * The tenant-separated storage path for this operation's output.
     */
    public function storagePath(string $relative): string
    {
        return TenantPath::for($this->tenant, 'operations/'.$this->id.'/'.$relative);
    }

    /**
     * The directory that holds every file of this operation.
     */
    public function storagePrefix(): string
    {
        return TenantPath::prefix($this->tenant).'operations/'.$this->id.'/';
    }
}
