<?php

declare(strict_types=1);

namespace Gcego\Actions\Handlers;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates the source model from validated input through fill(), so the
 * model's $fillable/$guarded, casts, mutators and events all apply. The
 * tenant column of tenant-owned models is set from the verified tenant,
 * never from input.
 */
final readonly class CreateRecord implements ActionHandler
{
    public function __construct(private SecurityConfig $security) {}

    public function handle(ActionContext $context): Model
    {
        $class = $context->action->model() ?? throw InvalidActionException::invalid($context->identity(), 'Create needs a blueprint Source with an Eloquent model.');
        $record = new $class;
        $connection = $context->action->transactionConnection();

        if ($connection !== null) {
            $record->setConnection($connection);
        }

        $record->fill($context->input->all());
        $column = (new TenantScope($this->security->tenantModels))->column($class);

        if ($column !== null && $context->tenant() !== null) {
            $record->setAttribute($column, $context->tenant()->key);
        }

        $record->save();

        return $record;
    }
}
