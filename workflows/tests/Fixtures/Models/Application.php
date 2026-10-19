<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Models;

use Gcego\Workflows\Models\Concerns\HasWorkflows;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property bool $documents
 * @property bool $complete
 */
final class Application extends Model
{
    use HasWorkflows;

    protected $fillable = ['name', 'documents', 'complete'];

    protected function casts(): array
    {
        return ['documents' => 'boolean', 'complete' => 'boolean'];
    }
}
