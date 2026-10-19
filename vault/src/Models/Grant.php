<?php

declare(strict_types=1);

namespace Gcego\Vault\Models;

use Gcego\Vault\Models\Concerns\HoldsAccess;
use Gcego\Vault\Models\Concerns\UsesVaultConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A permission granted directly to a model (usually a user), globally or in one tenant. Rows are never
 * deleted: revoking or expiring one ends it and keeps it as history.
 * Written only through AssignmentManager.
 *
 * @property int $permission_id
 */
class Grant extends Model
{
    use HoldsAccess, UsesVaultConnection;

    protected $table = 'gcego_vault_grants';

    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    protected function casts(): array
    {
        return [...$this->holdsAccessCasts(), 'permission_id' => 'integer'];
    }
}
