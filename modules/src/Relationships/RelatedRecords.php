<?php

declare(strict_types=1);

namespace Gcego\Modules\Relationships;

use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Data\Source;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Records\RecordAuthorizer;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantScope;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Validation\ValidationException;

/**
 * The work of relationship actions, run by gcego/actions after it has
 * resolved, authorized, confirmed and validated the action (inside its
 * transaction). Each operation first re-resolves the parent with the
 * "update" ability: the browser names records, never relationships.
 *
 *  - create: a new related record from the validated form input, linked to
 *    the parent through the relationship (the foreign key or pivot is set
 *    here, never taken from input);
 *  - attach (BelongsToMany): an existing record, looked up through the
 *    related resource's Source (scoped) and authorized (view) first;
 *  - detach (BelongsToMany): the action's record, already constrained to
 *    the parent by the relation's Source.
 */
final readonly class RelatedRecords
{
    public function __construct(
        private ParentRecords $parents,
        private Container $container,
        private SecurityConfig $security,
    ) {}

    public function create(ActionContext $context, RelationReference $relation): Model
    {
        $parent = $this->parents->resolve($relation, 'update');
        $relationship = $this->parents->relationship($parent, $relation);
        $record = $relationship->getRelated()->newInstance();
        $record->fill($context->input->all());
        $column = (new TenantScope($this->security->tenantModels))->column($record::class);

        if ($column !== null && $context->tenant() !== null) {
            $record->setAttribute($column, $context->tenant()->key);
        }

        if ($relationship instanceof HasOneOrMany) {
            $relationship->save($record);
        } elseif ($relationship instanceof BelongsToMany) {
            $record->save();
            $relationship->attach($record->getKey());
        } else {
            throw InvalidActionException::invalid($context->identity(), sprintf('records cannot be created through a %s relationship.', class_basename($relationship)));
        }

        $this->parents->forget();

        return $record;
    }

    /**
     * @param  Source  $candidates  the related records the user may choose from (not constrained to the parent)
     *
     * @throws AccessDeniedException|ValidationException
     */
    public function attach(ActionContext $context, RelationReference $relation, Source $candidates): Model
    {
        $parent = $this->parents->resolve($relation, 'update');
        $relationship = $this->belongsToMany($context, $parent, $relation);
        $key = $context->input->get('record');

        if (! is_string($key) && ! is_int($key)) {
            throw ValidationException::withMessages(['record' => [__('gcego-modules::messages.attach.invalid')]]);
        }

        [$decision, $record] = $this->container->make(RecordAuthorizer::class)->inspect($candidates, $key, 'view');

        if (! $decision->allowed || ! $record instanceof Model) {
            // Unknown, out of scope and forbidden records look the same.
            throw ValidationException::withMessages(['record' => [__('gcego-modules::messages.attach.invalid')]]);
        }

        $relationship->syncWithoutDetaching([$record->getKey()]);
        $this->parents->forget();

        return $record;
    }

    public function detach(ActionContext $context, RelationReference $relation): void
    {
        $parent = $this->parents->resolve($relation, 'update');
        $relationship = $this->belongsToMany($context, $parent, $relation);
        $record = $context->record;

        if (! $record instanceof Model) {
            throw InvalidActionException::invalid($context->identity(), 'Detach needs a record.');
        }

        $relationship->detach($record->getKey());
        $this->parents->forget();
    }

    /**
     * @return BelongsToMany<Model, Model>
     */
    private function belongsToMany(ActionContext $context, Model $parent, RelationReference $relation): BelongsToMany
    {
        $relationship = $this->parents->relationship($parent, $relation);

        return $relationship instanceof BelongsToMany ? $relationship : throw InvalidActionException::invalid($context->identity(), 'Attach and Detach need a BelongsToMany relationship.');
    }
}
