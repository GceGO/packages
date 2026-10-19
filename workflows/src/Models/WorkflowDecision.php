<?php

declare(strict_types=1);

namespace Gcego\Workflows\Models;

use Gcego\Workflows\Approvals\Decision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One reviewer's decision on an approval stage (once per round, enforced by a unique index).
 *
 * @property int $id
 * @property string $approval
 * @property string $stage
 * @property int $round
 * @property Decision $decision
 * @property string|null $comment
 * @property Carbon|null $created_at
 */
class WorkflowDecision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gcego_workflow_decisions';

    protected $guarded = ['*'];

    protected $hidden = ['tenant', 'actor_type', 'actor_id', 'comment'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['decision' => Decision::class, 'round' => 'integer'];
    }
}
