<?php

declare(strict_types=1);

namespace Gcego\Modules\Definitions;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Data\Source;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Operation;
use Gcego\Modules\Relationships\RelationReference;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Table;

/**
 * A normalized relationship manager: records related to one parent record,
 * through $relationship on the parent resource's model. Its pages live at
 * "<parent path>/{parent}/<slug>"; $source is the related records' Source,
 * already constrained to the parent (ParentScope).
 */
final readonly class RelationDefinition extends RecordSetDefinition
{
    /** The route parameter holding the parent record's key. */
    public const string ParentParameter = 'parent';

    /**
     * @param  list<Operation>  $operations
     * @param  list<Action|ActionGroup>  $actions
     * @param  list<Requirement>  $requirements
     * @param  array<string, string>  $permissions
     * @param  class-string|null  $model
     */
    public function __construct(
        string $module,
        string $name,
        string $pageBase,
        string $path,
        string $label,
        string $pluralLabel,
        ?string $model,
        Source $source,
        ?Table $table,
        ?Form $form,
        ?Infolist $infolist,
        ?Action $createAction,
        ?Action $editAction,
        array $actions,
        array $operations,
        array $requirements,
        array $permissions,
        ?string $recordKey,
        ?string $recordTitle,
        ?string $icon,
        public string $resource,
        public string $relationship,
        public Source $parentSource,
        public string $parentRecordKey,
        public string $parentPageBase,
        public RelationReference $reference,
    ) {
        parent::__construct($module, $name, $pageBase, $path, $label, $pluralLabel, $model, $source, $table, $form, $infolist, $createAction, $editAction, $actions, $operations, $requirements, $permissions, $recordKey, $recordTitle, $icon);
    }

    /**
     * "relation:academics.subjects.topics".
     */
    public function identity(): string
    {
        return 'relation:'.$this->module.'.'.$this->resource.'.'.$this->name;
    }
}
