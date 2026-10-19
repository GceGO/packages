<?php

declare(strict_types=1);

namespace Gcego\Actions\Handlers;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionContext;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Model::delete(): a soft delete for SoftDeletes models; model events fire.
 */
final class DeleteRecord implements ActionHandler
{
    public function handle(ActionContext $context): Model
    {
        $record = $context->record instanceof Model ? $context->record : throw InvalidActionException::invalid($context->identity(), 'Delete needs an Eloquent record.');

        if ($record->delete() === false) {
            throw new RuntimeException('The record was not deleted (a model event cancelled it).');
        }

        return $record;
    }
}
