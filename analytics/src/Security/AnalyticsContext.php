<?php

declare(strict_types=1);

namespace Gcego\Analytics\Security;

use Gcego\Analytics\Support\Keys;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Who is asking, in which tenant and time zone: built by server code from
 * the authenticated user and the verified tenant (or, for scheduled
 * deliveries, from the stored owner and tenant, re-verified), never from
 * request input. Every calculation runs inside this context.
 */
final readonly class AnalyticsContext
{
    public string $correlation;

    public function __construct(
        public ?Authenticatable $user,
        public ?string $tenant,
        public string $timezone,
        ?string $correlation = null,
    ) {
        $this->correlation = $correlation ?? (string) Str::ulid();
    }

    /**
     * The audience of cached results: the user and tenant (results are never shared across them).
     */
    public function audience(): string
    {
        $user = $this->user;
        $who = $user === null ? 'guest' : ($user instanceof Model ? $user->getMorphClass() : $user::class).'#'.Keys::string($user->getAuthIdentifier());

        return $who.'@'.($this->tenant ?? '-');
    }

    public function withTimezone(string $timezone): self
    {
        return new self($this->user, $this->tenant, $timezone, $this->correlation);
    }
}
