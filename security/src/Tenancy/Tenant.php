<?php

declare(strict_types=1);

namespace Gcego\Security\Tenancy;

use Gcego\Security\Exceptions\InvalidPermissionException;

/**
 * A tenant (institution, school, organization, workspace...), described by
 * the application's TenantResolver. The framework never assumes its model.
 */
final readonly class Tenant
{
    public function __construct(
        public string $type,
        public string|int $key,
        public mixed $model = null,
        public ?string $name = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $type) !== 1) {
            throw InvalidPermissionException::invalid('Tenant', 'the type must be a lowercase identifier such as "institution".');
        }
    }

    public function is(?self $other): bool
    {
        return $other !== null && $other->type === $this->type && (string) $other->key === (string) $this->key;
    }

    public function id(): string
    {
        return $this->type.':'.$this->key;
    }
}
