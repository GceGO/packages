<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Fixtures\Models;

use Gcego\Workflows\Models\Concerns\HasWorkflows;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $institution_id
 * @property string $name
 * @property ?string $code
 * @property ?string $secret_note
 * @property bool $published
 */
final class Subject extends Model
{
    use HasWorkflows;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
