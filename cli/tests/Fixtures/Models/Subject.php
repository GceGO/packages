<?php

declare(strict_types=1);

namespace Gcego\Cli\Tests\Fixtures\Models;

use Gcego\Cli\Tests\Fixtures\Enums\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * A model with every field type the generators map, plus attributes they must never expose.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $institution_id
 */
final class Subject extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_published', 'published_on', 'credits', 'status', 'password', 'api_token'];

    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_on' => 'date', 'credits' => 'integer', 'status' => Status::class];
    }
}
