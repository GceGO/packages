<?php

declare(strict_types=1);

namespace Gcego\Actions\Handlers;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionContext;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * SoftDeletes::restore() on a record resolved among trashed rows.
 */
final class RestoreRecord implements ActionHandler
{
    public function handle(ActionContext $context): Model
    {
        $record = $context->record;

        if (! $record instanceof Model || ! method_exists($record, 'restore')) {
            throw InvalidActionException::invalid($context->identity(), 'Restore needs a model that uses SoftDeletes.');
        }

        if ($record->restore() === false) {
            throw new RuntimeException('The record was not restored (a model event cancelled it).');
        }

        return $record;
    }
}
