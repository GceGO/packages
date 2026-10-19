<?php

declare(strict_types=1);

namespace Gcego\Actions\Handlers;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionContext;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * SoftDeletes::forceDelete() on a record resolved among trashed rows: permanent.
 */
final class ForceDeleteRecord implements ActionHandler
{
    public function handle(ActionContext $context): Model
    {
        $record = $context->record;

        if (! $record instanceof Model || ! method_exists($record, 'restore')) {
            throw InvalidActionException::invalid($context->identity(), 'ForceDelete needs a model that uses SoftDeletes.');
        }

        if ($record->forceDelete() === false) {
            throw new RuntimeException('The record was not deleted (a model event cancelled it).');
        }

        return $record;
    }
}
