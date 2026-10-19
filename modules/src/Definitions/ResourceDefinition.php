<?php

declare(strict_types=1);

namespace Gcego\Modules\Definitions;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Data\Source;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Operation;
use Gcego\Pages\Page;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Table;

/**
 * A normalized resource ("resource:academics.subjects").
 */
final readonly class ResourceDefinition extends RecordSetDefinition
{
    /**
     * @param  list<Operation>  $operations
     * @param  list<Action|ActionGroup>  $actions
     * @param  list<Requirement>  $requirements
     * @param  array<string, string>  $permissions
     * @param  class-string|null  $model
     * @param  list<RelationDefinition>  $relations
     * @param  list<Page>  $pages  custom pages, already named and placed
     * @param  bool  $navigationBadge  show the record count as the navigation badge
     * @param  string|null  $navigationBadgeVariant  its color (NavigationPresets::BadgeVariants)
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
        public bool $navigation,
        public string $navigationLabel,
        public ?int $navigationOrder,
        public array $relations,
        public array $pages,
        public bool $navigationBadge = false,
        public ?string $navigationBadgeVariant = null,
    ) {
        parent::__construct($module, $name, $pageBase, $path, $label, $pluralLabel, $model, $source, $table, $form, $infolist, $createAction, $editAction, $actions, $operations, $requirements, $permissions, $recordKey, $recordTitle, $icon);
    }

    /**
     * "resource:academics.subjects".
     */
    public function identity(): string
    {
        return 'resource:'.$this->module.'.'.$this->name;
    }

    /**
     * Whether the resource has a page for the operation: generated, or a custom page that overrides it.
     */
    public function exposes(Operation $operation): bool
    {
        if ($this->has($operation)) {
            return true;
        }

        foreach ($this->pages as $page) {
            if ($page->pageName() === $this->pageName($operation)) {
                return true;
            }
        }

        return false;
    }

    public function relation(string $name): ?RelationDefinition
    {
        foreach ($this->relations as $relation) {
            if ($relation->name === $name || $relation->relationship === $name) {
                return $relation;
            }
        }

        return null;
    }
}
