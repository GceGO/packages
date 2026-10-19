<?php

declare(strict_types=1);

namespace Gcego\Modules\Definitions;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Data\Source;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Operation;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Table;

/**
 * The normalized, trusted description of a resource or relation: everything
 * the page factory needs, with names, paths, operations and requirements
 * already decided. Built by the ModuleCompiler; immutable.
 */
abstract readonly class RecordSetDefinition
{
    /**
     * @param  list<Operation>  $operations
     * @param  list<Action|ActionGroup>  $actions  record, bulk and custom actions (not Create/Edit)
     * @param  list<Requirement>  $requirements  module, resource (and relation) requirements
     * @param  array<string, string>  $permissions  operation => permission
     * @param  class-string|null  $model
     */
    public function __construct(
        public string $module,
        public string $name,
        public string $pageBase,
        public string $path,
        public string $label,
        public string $pluralLabel,
        public ?string $model,
        public Source $source,
        public ?Table $table,
        public ?Form $form,
        public ?Infolist $infolist,
        public ?Action $createAction,
        public ?Action $editAction,
        public array $actions,
        public array $operations,
        public array $requirements,
        public array $permissions,
        public ?string $recordKey,
        public ?string $recordTitle,
        public ?string $icon,
    ) {}

    public function has(Operation $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    /**
     * The page name of an operation: the base for the list ("academics.subjects"), "<base>.<operation>" otherwise.
     */
    public function pageName(Operation $operation): string
    {
        return $operation === Operation::Index ? $this->pageBase : $this->pageBase.'.'.$operation->value;
    }

    /**
     * The route name of an operation (below the pages name prefix): "academics.subjects.index".
     */
    public function routeName(Operation $operation): string
    {
        return $this->pageBase.'.'.$operation->value;
    }

    /**
     * The URL path of an operation's page (below the pages prefix).
     */
    public function pagePath(Operation $operation): string
    {
        return match ($operation) {
            Operation::Index => $this->path,
            Operation::Create => $this->path.'/create',
            Operation::View => $this->path.'/{record}',
            Operation::Edit => $this->path.'/{record}/edit',
        };
    }

    /**
     * Registry names of this set's generated pages ("academics-subjects-create").
     *
     * @return list<string>
     */
    public function pageRegistryNames(): array
    {
        return array_map(fn (Operation $operation): string => str_replace('.', '-', $this->pageName($operation)), $this->operations);
    }
}
