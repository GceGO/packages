<?php

declare(strict_types=1);

namespace Gcego\Tables\Support;

use Gcego\Security\Authorization\AuthorizationContext;

/**
 * Who stored preferences and saved views belong to: guard, user key and
 * tenant ("" without a tenant). User keys are strings, so integer, UUID and
 * ULID keys all work. Guests own nothing.
 */
final readonly class TableOwner
{
    public function __construct(public string $guard, public string $key, public string $tenant = '')
    {
        if ($guard === '' || $key === '' || strlen($guard) > 64 || strlen($key) > 191 || strlen($tenant) > 191) {
            throw new \InvalidArgumentException('A table owner needs a guard (≤ 64) and a user key (≤ 191); tenants are at most 191 characters.');
        }
    }

    public static function from(AuthorizationContext $context): ?self
    {
        $key = $context->userKey();

        if ($key === null) {
            return null;
        }

        $tenant = $context->tenant;

        return new self($context->guard, (string) $key, $tenant === null ? '' : $tenant->type.':'.$tenant->key);
    }
}
