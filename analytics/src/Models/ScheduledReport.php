<?php

declare(strict_types=1);

namespace Gcego\Analytics\Models;

use Gcego\Analytics\Support\Keys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A report delivered on a schedule (daily, weekly or monthly at a local
 * time) to its owner and recipients. Every run re-checks the owner's and
 * each recipient's access; it never relies on the access they had when it
 * was created.
 *
 * @property int $id
 * @property string $ulid
 * @property string $report
 * @property string $owner_type
 * @property string $owner_id
 * @property string|null $tenant
 * @property array<string, mixed>|null $filters
 * @property string|null $range
 * @property string $format
 * @property string $frequency
 * @property int|null $day
 * @property string $time
 * @property string $timezone
 * @property list<string> $recipients
 * @property bool $active
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property string|null $last_status
 * @property int $failures
 */
class ScheduledReport extends Model
{
    public const array Frequencies = ['daily', 'weekly', 'monthly'];

    protected $table = 'gcego_analytics_schedules';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'owner_type', 'owner_id', 'tenant', 'recipients'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function ownedBy(?Model $user): bool
    {
        return $user !== null && $this->owner_type === $user->getMorphClass() && $this->owner_id === Keys::string($user->getKey());
    }

    protected function casts(): array
    {
        return ['filters' => 'array', 'recipients' => 'array', 'active' => 'boolean', 'day' => 'integer', 'failures' => 'integer', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime'];
    }
}
