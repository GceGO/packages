<?php

declare(strict_types=1);

namespace Gcego\Vault\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $isbn
 * @property int|null $owner_id
 */
final class Book extends Model
{
    protected $guarded = [];
}
