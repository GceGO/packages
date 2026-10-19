<?php

declare(strict_types=1);

namespace Gcego\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The first result of an idempotent command (keyed by a hash of command, target, actor and key).
 *
 * @property string $key
 * @property array<string, mixed> $result
 */
class WorkflowCommand extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gcego_workflow_commands';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
