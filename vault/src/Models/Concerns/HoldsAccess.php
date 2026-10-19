<?php

declare(strict_types=1);

namespace Gcego\Vault\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Shared by role assignments and direct permission grants: who holds it,
 * where (tenant), until when, who granted and who revoked it.
 *
 * Active means: not revoked, and not expired *now*. Expiry is checked at read
 * time, so an expired grant stops working before any cleanup job runs.
 *
 * @property int $id
 * @property string $model_type
 * @property string $model_id
 * @property string $tenant
 * @property Carbon|null $expires_at
 * @property string $source
 * @property int|null $request_id
 * @property string|null $reason
 * @property string|null $granted_by_type
 * @property string|null $granted_by_id
 * @property Carbon|null $revoked_at
 * @property string|null $ended_as
 * @property string|null $revoked_by_type
 * @property string|null $revoked_by_id
 * @property string|null $revoke_reason
 * @property string|null $active_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
trait HoldsAccess
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo('holder', 'model_type', 'model_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function grantor(): MorphTo
    {
        return $this->morphTo('grantor', 'granted_by_type', 'granted_by_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = Date::now();

        return $query->whereNull('revoked_at')->where(static fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHeldBy(Builder $query, string $type, string $id): Builder
    {
        return $query->where('model_type', $type)->where('model_id', $id);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isTemporary(): bool
    {
        return $this->expires_at !== null;
    }

    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => $this->ended_as ?? 'revoked',
            $this->expires_at !== null && ! $this->expires_at->isFuture() => 'expired',
            default => 'active',
        };
    }

    protected function holdsAccessCasts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'request_id' => 'integer'];
    }
}
