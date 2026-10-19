<?php

declare(strict_types=1);

namespace Gcego\Tables\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A UUID-keyed model.
 *
 * @property string $id
 * @property string $title
 */
final class Document extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'institution_id'];
}
