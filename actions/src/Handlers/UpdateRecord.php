<?php

declare(strict_types=1);

namespace Gcego\Actions\Handlers;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Updates the resolved record with validated input through fill() and save().
 */
final class UpdateRecord implements ActionHandler
{
    public function handle(ActionContext $context): Model
    {
        $record = $context->record instanceof Model ? $context->record : throw InvalidActionException::invalid($context->identity(), 'Edit needs an Eloquent record.');
        $record->fill($context->input->all())->save();

        return $record;
    }
}
