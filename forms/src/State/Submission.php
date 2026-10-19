<?php

declare(strict_types=1);

namespace Gcego\Forms\State;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A processed submission: attribute data, and callbacks that persist
 * PersistsWithRecord fields once the record is saved.
 */
final readonly class Submission
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<Closure(Model): void>  $afterSave
     */
    public function __construct(public array $data, public array $afterSave = []) {}
}
