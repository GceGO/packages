<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model without a policy.
 *
 * @property int $id
 * @property string $title
 */
final class Report extends Model
{
    protected $guarded = [];
}
