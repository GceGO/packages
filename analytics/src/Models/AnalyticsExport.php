<?php

declare(strict_types=1);

namespace Gcego\Analytics\Models;

use Gcego\Analytics\Support\Keys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A report export. Its file lives on a private disk under an unguessable
 * path and is served only through ExportManager::download (owner or
 * listed recipient, report access re-checked, same tenant, not expired).
 *
 * @property int $id
 * @property string $ulid
 * @property string $report
 * @property string $format
 * @property string $status
 * @property string $owner_type
 * @property string $owner_id
 * @property string|null $tenant
 * @property array<string, mixed> $request
 * @property list<string>|null $recipients
 * @property int|null $schedule_id
 * @property int|null $rows
 * @property string $disk
 * @property string|null $path
 * @property int|null $size
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 */
class AnalyticsExport extends Model
{
    public const string Pending = 'pending';

    public const string Running = 'running';

    public const string Completed = 'completed';

    public const string Failed = 'failed';

    public const string Expired = 'expired';

    protected $table = 'gcego_analytics_exports';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'owner_type', 'owner_id', 'tenant', 'request', 'recipients', 'disk', 'path', 'schedule_id'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<ScheduledReport, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class, 'schedule_id');
    }

    public function ownedBy(?Model $user): bool
    {
        return $user !== null && $this->owner_type === $user->getMorphClass() && $this->owner_id === Keys::string($user->getKey());
    }

    public function sentTo(?Model $user): bool
    {
        return $user !== null && in_array($user->getMorphClass().'#'.Keys::string($user->getKey()), $this->recipients ?? [], true);
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::Completed && $this->path !== null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    protected function casts(): array
    {
        return ['request' => 'array', 'recipients' => 'array', 'rows' => 'integer', 'size' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
