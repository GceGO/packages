<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

use Gcego\Security\Exceptions\MissingAuthorizationContextException;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who is acting, on which guard, for which tenant: the input of every check.
 *
 * Immutable and per call. It is built from Laravel's guard and the scoped
 * TenantContext (ContextFactory), never stored in a shared service, and
 * never serialized (queued work carries a SecuredOperation instead).
 */
final readonly class AuthorizationContext
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public ?Authenticatable $user,
        public string $guard,
        public ?Tenant $tenant = null,
        public ?TenantMembership $membership = null,
        public array $metadata = [],
    ) {
        if ($membership !== null) {
            if ($tenant === null || ! $membership->tenant->is($tenant)) {
                throw MissingAuthorizationContextException::because('A membership needs its own tenant in the context.');
            }

            if ($user === null || (string) $membership->userKey !== (string) self::keyOf($user)) {
                throw MissingAuthorizationContextException::because('The membership belongs to another user.');
            }
        }
    }

    public static function guest(string $guard): self
    {
        return new self(null, $guard);
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function userKey(): string|int|null
    {
        return $this->user === null ? null : self::keyOf($this->user);
    }

    /**
     * Identifies everything a permission decision depends on; used for request-scoped caching.
     */
    public function fingerprint(): string
    {
        return implode('|', [
            $this->guard,
            $this->user === null ? 'guest' : $this->user::class.'#'.$this->userKey(),
            $this->tenant?->id() ?? '-',
            $this->membership === null ? '-' : md5(implode(',', $this->membership->roles).'/'.implode(',', $this->membership->permissions)),
        ]);
    }

    /**
     * A user's identifier, normalized for comparisons and cache keys.
     */
    public static function keyOf(Authenticatable $user): string|int
    {
        $key = $user->getAuthIdentifier();

        return is_int($key) || is_string($key) ? $key : (string) json_encode($key);
    }
}
