<?php

declare(strict_types=1);

namespace Gcego\Workflows\Integration\Actions;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\WorkflowConflictException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A gcego/actions handler starting a workflow for the record as the acting user:
 *
 *     final class StartRegistration extends StartWorkflow
 *     {
 *         protected function workflow(): string { return 'institution-registration'; }
 *     }
 */
abstract class StartWorkflow implements ActionHandler
{
    abstract protected function workflow(): string;

    public function handle(ActionContext $context): WorkflowInstance
    {
        $user = $context->user();
        $record = $context->record;

        if (! $user instanceof Model || ! $record instanceof Model) {
            throw WorkflowNotFoundException::make('record');
        }

        try {
            return app(WorkflowEngine::class)->start($this->workflow(), $record, $user);
        } catch (WorkflowConflictException $exception) {
            throw ValidationException::withMessages(['workflow' => $exception->getMessage()]);
        }
    }
}
