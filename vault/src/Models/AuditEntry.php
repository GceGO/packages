<?php

declare(strict_types=1);

namespace Gcego\Vault\Models;

use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Models\Concerns\UsesVaultConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One entry of the append-only access audit log (written by AuditLog only).
 *
 * @property int $id
 * @property AuditEvent $event
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $actor_name
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_name
 * @property string|null $target
 * @property string $tenant
 * @property string|null $reason
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 */
class AuditEntry extends Model
{
    use UsesVaultConnection;

    public const null UPDATED_AT = null;

    protected $table = 'gcego_vault_audits';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['event' => AuditEvent::class, 'payload' => 'array'];
    }
}
