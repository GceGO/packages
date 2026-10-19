<?php

declare(strict_types=1);

namespace Gcego\Analytics\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One student's attempt at an assessment (fixture of an academic dataset).
 *
 * @property int $id
 * @property int $user_id
 * @property int $subject_id
 * @property string $level
 * @property string $kind
 * @property string $status
 * @property float|null $score
 * @property int $time_spent
 * @property string|null $email
 */
final class AssessmentResult extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'score' => 'float'];
    }
}
