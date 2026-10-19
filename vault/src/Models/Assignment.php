<?php

declare(strict_types=1);

namespace Gcego\Vault\Models;

use Gcego\Vault\Models\Concerns\HoldsAccess;
use Gcego\Vault\Models\Concerns\UsesVaultConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A role held by a model (usually a user), globally or in one tenant. Rows are never
 * deleted: revoking or expiring one ends it and keeps it as history.
 * Written only through AssignmentManager.
 *
 * @property int $role_id
 */
class Assignment extends Model
{
    use HoldsAccess, UsesVaultConnection;

    protected $table = 'gcego_vault_assignments';

    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    protected function casts(): array
    {
        return [...$this->holdsAccessCasts(), 'role_id' => 'integer'];
    }
}
